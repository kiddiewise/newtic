<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GoogleMerchantSetting;
use App\Models\GoogleMerchantSyncItem;
use App\Models\Product;
use App\Models\User;
use App\Support\GoogleMerchantProduct;
use App\Support\GoogleMerchantSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMerchantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Surf Kurşun', 'slug' => 'surf-kursun', 'is_active' => true]);
    }

    private function product(Category $category, array $attrs = []): Product
    {
        return Product::create(array_merge([
            'sku' => 'POL-125',
            'name' => 'Surf Kurşun 125 gr',
            'slug' => 'surf-kursun-125',
            'primary_category_id' => $category->id,
            'price' => '80.00', 'compare_at_price' => '100.00',
            'stock_quantity' => 8, 'tracks_stock' => true, 'is_active' => true,
            'is_demo' => false,
            'description' => 'Deniz balıkçılığı için kurşun',
            'image_path' => 'storage/products/surf-125.webp',
            'google_brand' => 'Polaris Kurşun',
            'gtin' => '8691234567890',
        ], $attrs));
    }

    private function connected(): GoogleMerchantSetting
    {
        $setting = GoogleMerchantSetting::current();
        $setting->update([
            'client_id' => '123456-sample.apps.googleusercontent.com',
            'client_secret' => 'oauth-test-private',
            'refresh_token' => 'refresh-private-token',
            'merchant_id' => '123456789',
            'data_source_name' => 'accounts/123456789/dataSources/98765',
        ]);
        return $setting->fresh();
    }

    public function test_google_admin_is_private_and_public_feed_excludes_inactive_and_demo_products(): void
    {
        $this->get(route('admin.google-merchant.edit'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.google-merchant.connect'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.google-merchant.edit'))->assertForbidden();

        $category = $this->category();
        $this->product($category, ['name' => 'Google için geçerli ürün']);
        $this->product($category, [
            'sku' => 'POL-PASIF', 'slug' => 'pasif-urun',
            'name' => 'Gizli ürün', 'is_active' => false,
        ]);
        $this->product($category, [
            'sku' => 'POL-DEMO', 'slug' => 'demo-urun',
            'name' => 'Demo ürün', 'is_demo' => true,
        ]);
        $feed = $this->get(route('google-merchant.feed'))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Google için geçerli ürün', $feed);
        $this->assertStringNotContainsString('Gizli ürün', $feed);
        $this->assertStringNotContainsString('Demo ürün', $feed);
        $this->assertSame(1, substr_count($feed, '<item>'));
        $this->assertStringContainsString('<g:price>100.00 TRY</g:price>', $feed);
        $this->assertStringContainsString('<g:sale_price>80.00 TRY</g:sale_price>', $feed);
        $this->assertStringContainsString('<g:availability>in_stock</g:availability>', $feed);
        $this->assertStringContainsString('<g:gtin>8691234567890</g:gtin>', $feed);
    }

    public function test_credentials_are_encrypted_and_never_echoed_in_html(): void
    {
        $this->actingAs($this->admin());
        $this->patch(route('admin.google-merchant.save'), [
            'client_id' => '123456-sample.apps.googleusercontent.com',
            'client_secret' => 'very-private-client-secret',
        ])->assertRedirect(route('admin.google-merchant.edit'))->assertSessionHasNoErrors();

        $setting = GoogleMerchantSetting::current();
        $this->assertTrue($setting->isConfigured());
        $this->assertFalse($setting->isConnected());
        $this->assertSame('very-private-client-secret', $setting->client_secret);
        $this->assertNotSame('very-private-client-secret',
            DB::table('google_merchant_settings')->first()->client_secret);
        $this->assertArrayNotHasKey('client_secret', $setting->toArray());
        $body = $this->get(route('admin.google-merchant.edit'))->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store, max-age=0')->getContent();
        $this->assertStringNotContainsString('very-private-client-secret', $body);
        $this->assertStringContainsString('Google Merchant Center', $body);
        $this->assertStringContainsString('google/merchant-products.xml', $body);
    }

    public function test_oauth_callback_rejects_invalid_state_and_exchanges_code_once(): void
    {
        $this->actingAs($this->admin());
        $setting = GoogleMerchantSetting::current();
        $setting->update([
            'client_id' => '123456-sample.apps.googleusercontent.com',
            'client_secret' => 'secret',
        ]);
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'short-lived-token',
            'refresh_token' => 'fresh-refresh-token',
            'scope' => 'https://www.googleapis.com/auth/content',
        ], 200)]);

        $response = $this->post(route('admin.google-merchant.connect'))->assertRedirect();
        $url = $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertSame('offline', $params['access_type']);
        $this->assertSame(route('admin.google-merchant.callback'), $params['redirect_uri']);

        $this->get(route('admin.google-merchant.callback', [
            'state' => 'invalid', 'code' => 'fake-code',
        ]))->assertSessionHasErrors('google');
        $this->assertFalse(GoogleMerchantSetting::current()->isConnected());
        Http::assertNothingSent();

        $this->post(route('admin.google-merchant.connect'))->assertRedirect();
        $pending = session('google_merchant_oauth');
        $this->get(route('admin.google-merchant.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertRedirect(route('admin.google-merchant.edit'))->assertSessionHasNoErrors();

        $this->assertTrue(GoogleMerchantSetting::current()->isConnected());
        $this->assertSame('fresh-refresh-token', GoogleMerchantSetting::current()->refresh_token);
        $this->assertStringNotContainsString('fresh-refresh-token',
            DB::table('google_merchant_settings')->first()->refresh_token);
        $this->get(route('admin.google-merchant.callback', [
            'state' => $pending['state'], 'code' => 'valid-code',
        ]))->assertSessionHasErrors('google');
    }

    public function test_merchant_account_is_verified_and_api_source_created_only_after_selection(): void
    {
        $this->actingAs($this->admin());
        $setting = $this->connected();
        $setting->update(['merchant_id' => null, 'data_source_name' => null]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'api-access'], 200),
            'https://merchantapi.googleapis.com/accounts/v1/accounts*' => Http::response([
                'accounts' => [['name' => 'accounts/123456789', 'accountName' => 'Polaris']],
            ], 200),
            'https://merchantapi.googleapis.com/datasources/v1/accounts/123456789/dataSources' =>
                Http::response(['name' => 'accounts/123456789/dataSources/555'], 200),
        ]);

        $this->post(route('admin.google-merchant.select'), ['account' => 'accounts/99999999'])
            ->assertSessionHasErrors('google');
        $this->assertNull(GoogleMerchantSetting::current()->merchant_id);

        $this->post(route('admin.google-merchant.accounts'))->assertSessionHasNoErrors();
        $this->get(route('admin.google-merchant.edit'))->assertOk()->assertSee('Polaris');
        $this->post(route('admin.google-merchant.select'), ['account' => 'accounts/123456789'])
            ->assertSessionHasNoErrors();

        $this->post(route('admin.google-merchant.prepare'))->assertSessionHasNoErrors();
        $this->assertSame('accounts/123456789/dataSources/555',
            GoogleMerchantSetting::current()->data_source_name);
        $this->post(route('admin.google-merchant.prepare'))->assertSessionHasErrors('google');
    }

    public function test_sync_sends_prices_stock_and_unique_product_id_and_skips_unchanged(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();
        $product = $this->product($category);
        $setting = $this->connected();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'api-access'], 200),
            'https://merchantapi.googleapis.com/products/v1/accounts/123456789/productInputs:insert*' =>
                Http::response(['offerId' => 'newtic-'.$product->id], 200),
        ]);

        $this->postJson(route('admin.google-merchant.start'))->assertOk()->assertJsonPath('status', 'running');
        $this->postJson(route('admin.google-merchant.step'))->assertOk()->assertJsonPath('sent', 1);
        $this->postJson(route('admin.google-merchant.step'))->assertOk()->assertJsonPath('done', true);
        $this->assertSame('completed', GoogleMerchantSetting::current()->sync_status);
        $this->assertDatabaseHas('google_merchant_sync_items', [
            'product_id' => $product->id, 'merchant_id' => '123456789', 'status' => 'synced',
        ]);
        Http::assertSent(function ($request) use ($product) {
            return str_contains($request->url(), '/productInputs:insert') &&
                ($request['offerId'] ?? null) === 'newtic-'.$product->id &&
                ($request['productAttributes']['price']['amountMicros'] ?? null) === '100000000' &&
                ($request['productAttributes']['salePrice']['amountMicros'] ?? null) === '80000000' &&
                ($request['productAttributes']['availability'] ?? null) === 'IN_STOCK' &&
                ($request['productAttributes']['gtins'][0] ?? null) === '8691234567890';
        });

        $this->postJson(route('admin.google-merchant.start'))->assertOk();
        $this->postJson(route('admin.google-merchant.step'))->assertOk()->assertJsonPath('unchanged', 1);
        $this->assertSame(1, GoogleMerchantSyncItem::query()->count());
    }

    public function test_feed_excludes_inactive_parent_categories_and_escapes_xml(): void
    {
        $parent = $this->category();
        $product = $this->product($parent, ['name' => 'Kurşun & <tehlikeli>']);
        $xml = $this->get(route('google-merchant.feed'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Kurşun &amp; &lt;tehlikeli&gt;', $xml);

        $parent->update(['is_active' => false]);
        $xml = $this->get(route('google-merchant.feed'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Kurşun &amp;', $xml);
        $this->assertSame(0, substr_count($xml, '<item>'));
    }
}