<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GoogleMerchantSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SeoSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Surf Kurşun',
            'slug' => 'surf-kursun',
            'description' => 'Kıyı avcılığında kullanılan özel surf kurşunları.',
            'is_active' => true,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::create([
            'sku' => 'SURF-125',
            'name' => 'Kurşun 125 gram',
            'slug' => 'kursun-125',
            'description' => 'Denizde kullanılabilen kaliteli olta kurşunu.',
            'primary_category_id' => $category->id,
            'image_path' => 'storage/products/125.webp',
            'price' => '119.90',
            'compare_at_price' => '149.90',
            'stock_quantity' => 9,
            'tracks_stock' => true,
            'is_active' => true,
            'is_demo' => false,
        ]);
    }

    private function jsonLd(string $html): array
    {
        $this->assertSame(1, preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches));
        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function seoInput(array $override = []): array
    {
        return array_merge([
            'site_name' => 'Polaris Kurşun',
            'title_suffix' => 'Polaris Kurşun',
            'home_title' => 'Surf ve Balıkçılık | Polaris Kurşun',
            'home_description' => 'İzmir üretimi olta kurşunu ve balıkçılık ürünleri.',
            'default_description' => 'Balıkçılık ekipmanları',
            'noindex' => '0',
            'nofollow' => '0',
        ], $override);
    }

    public function test_temporary_hostinger_domain_always_forces_noindex_and_never_offers_sitemap(): void
    {
        $stage = 'https://powderblue-dinosaur-538060.hostingersite.com';
        $response = $this->get($stage.'/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow,noarchive">', $response->getContent());
        $this->assertStringContainsString('<link rel="canonical" href="'.$stage.'/">', $response->getContent());

        $this->get($stage.'/robots.txt')->assertOk()->assertSee('Disallow: /', false);
        $this->get($stage.'/sitemap.xml')->assertNotFound();

        SeoSetting::query()->create($this->seoInput(['noindex' => false]));
        $this->get($stage.'/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_live_domain_uses_its_own_origin_not_old_hostinger_or_old_app_url(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $homepage = $this->get('https://new-store.example/')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://new-store.example/">', $homepage);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $homepage);
        $this->assertStringContainsString('https://new-store.example/#organization', $homepage);
        $this->assertStringNotContainsString('hostingersite.com', $homepage);

        $html = $this->get('https://new-store.example/urun/'.$product->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://new-store.example/urun/kursun-125">', $html);
        $this->assertStringContainsString('https://new-store.example/storage/products/125.webp', $html);
        $this->assertStringNotContainsString('hostingersite.com', $html);

        $sitemap = $this->get('https://new-store.example/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('https://new-store.example/sitemap/products/1.xml', $sitemap);
        $this->assertStringNotContainsString('hostingersite.com', $sitemap);

        $productsMap = $this->get('https://new-store.example/sitemap/products/1.xml')->assertOk()->getContent();
        $this->assertStringContainsString('https://new-store.example/urun/kursun-125', $productsMap);

        $this->get('https://new-store.example/robots.txt')->assertOk()
            ->assertSee('Sitemap: https://new-store.example/sitemap.xml');
    }

    public function test_seo_admin_is_authorized_and_can_edit_homepage_and_index_flags(): void
    {
        $this->get(route('admin.seo.edit'))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.seo.update'), $this->seoInput())->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.seo.edit'))->assertForbidden();
        $this->patch(route('admin.seo.update'), $this->seoInput())->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.seo.edit'))->assertOk()
            ->assertSee('SEO Yönetimi')->assertSee('Noindex')->assertSee('Google Search Console')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->patch(route('admin.seo.update'), $this->seoInput([
            'site_name' => 'Polaris Av',
            'title_suffix' => 'Polaris Av',
            'noindex' => '1',
            'nofollow' => '1',
            'google_verification' => 'abDEF_09876',
            'pages' => ['cart' => [
                'title' => 'Sepetim | Polaris Av',
                'description' => 'Alışveriş sepetini görüntüle',
            ]],
        ]))->assertRedirect(route('admin.seo.edit'))->assertSessionHasNoErrors();

        $this->assertTrue(SeoSetting::current()->noindex);
        $this->assertTrue(SeoSetting::current()->nofollow);
        $home = $this->get('https://polaris-example.com/')->assertOk()->getContent();
        $this->assertStringContainsString('Surf ve Balıkçılık | Polaris Kurşun', $home);
        $this->assertStringContainsString('google-site-verification', $home);
        $this->assertStringContainsString('content="noindex,nofollow,noarchive"', $home);

        $cart = $this->get('https://polaris-example.com/sepet')->assertOk()->getContent();
        $this->assertStringContainsString('Sepetim | Polaris Av', $cart);
        $this->assertStringNotContainsString('rel="canonical"', $cart);
        $this->get('https://polaris-example.com/sitemap.xml')->assertNotFound();
    }

    public function test_product_json_ld_uses_real_price_stock_image_and_only_approved_non_test_ratings(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $initial = $this->jsonLd($this->get('https://shop.example/urun/kursun-125')->assertOk()->getContent());
        $productNode = collect($initial['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('119.90', $productNode['offers']['price']);
        $this->assertSame('TRY', $productNode['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $productNode['offers']['availability']);
        $this->assertSame('SURF-125', $productNode['sku']);
        $this->assertStringContainsString('shop.example/storage/products/125.webp', $productNode['image'][0]);
        $this->assertArrayNotHasKey('aggregateRating', $productNode);

        $buyer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'PK-SEO-'.Str::upper(Str::random(10)),
            'checkout_token' => (string) Str::uuid(),
            'user_id' => $buyer->id,
            'customer_name' => $buyer->name,
            'customer_email' => $buyer->email,
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi 30 Sokak No 5',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
            'subtotal_cents' => 11990,
            'shipping_cents' => 0,
            'total_cents' => 11990,
            'currency' => 'TRY',
            'is_test' => false,
            'placed_at' => now(),
        ]);
        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'rating' => 4, 'body' => 'Ürün gayet kullanışlı.', 'status' => 'approved', 'is_test' => false,
        ]);
        $buyer2 = User::factory()->create();
        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $buyer2->id,
            'order_id' => $order->id,
            'rating' => 5, 'body' => 'Test yorumu.', 'status' => 'approved', 'is_test' => true,
        ]);

        $rated = $this->jsonLd($this->get('https://shop.example/urun/kursun-125')->assertOk()->getContent());
        $ratedNode = collect($rated['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame(4.0, (float) $ratedNode['aggregateRating']['ratingValue']);
        $this->assertSame(1, $ratedNode['aggregateRating']['ratingCount']);

        $product->update(['price' => '159.00', 'compare_at_price' => null, 'stock_quantity' => 0]);
        $outOfStock = $this->jsonLd($this->get('https://shop.example/urun/kursun-125')->assertOk()->getContent());
        $changed = collect($outOfStock['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('159.00', $changed['offers']['price']);
        $this->assertSame('https://schema.org/OutOfStock', $changed['offers']['availability']);
    }

    public function test_category_seo_can_be_edited_and_child_products_excluded_from_sitemap(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->actingAs($this->admin());
        $this->patch(route('admin.categories.update', $category), [
            'name' => 'Surf Kurşun',
            'description' => 'Kıyı ürünleri',
            'sort_order' => 0,
            'is_active' => '1',
            'seo_title' => 'En İyi Surf Kurşunları | Polaris',
            'seo_description' => 'Gramajlara göre kıyı surf kurşunları.',
            'seo_noindex' => '1',
        ])->assertSessionHasNoErrors();

        $body = $this->get('https://shop.example/kategori/surf-kursun')->assertOk()->getContent();
        $this->assertStringContainsString('En İyi Surf Kurşunları | Polaris', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow,noarchive">', $body);

        $sitemap = $this->get('https://shop.example/sitemap/products/1.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/urun/kursun-125', $sitemap);

        $feed = $this->get('https://shop.example/google/merchant-products.xml')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('newtic-'.$product->id, $feed);

        $product->update(['seo_noindex' => true]);
        $this->assertTrue($product->fresh()->seo_noindex);
    }

    public function test_seo_social_media_upload_is_validated_and_stored_safely(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->patch(route('admin.seo.update'), array_merge($this->seoInput(), [
            'social_image' => UploadedFile::fake()->image('seo-preview.jpg', 1200, 630),
        ]))->assertSessionHasNoErrors();

        $setting = SeoSetting::current();
        $this->assertStringStartsWith('storage/seo/', $setting->social_image_path);
        Storage::disk('public')->assertExists(substr($setting->social_image_path, 8));
        $html = $this->get('https://real.example/')->assertOk()->getContent();
        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('https://real.example/storage/seo/', $html);
    }
}
