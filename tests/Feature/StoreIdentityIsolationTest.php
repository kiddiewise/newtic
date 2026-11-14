<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MegaMenuSetting;
use App\Models\Product;
use App\Models\SeoSetting;
use App\Support\StoreIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StoreIdentityIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_has_no_other_merchants_contact_details_and_starts_private(): void
    {
        // Running default seeders must not import another shop's demo data.
        $this->seed();
        $this->assertDatabaseCount('products', 0);

        $menu = MegaMenuSetting::current();
        $this->assertNull($menu->instagram_url);
        $this->assertNull($menu->youtube_url);
        $this->assertNull($menu->facebook_url);
        $this->assertNull($menu->whatsapp_number);
        $this->assertSame('', (string) $menu->footer_text);

        $seo = SeoSetting::current();
        $this->assertTrue($seo->noindex);
        $this->assertSame(config('app.name'), $seo->site_name);

        $this->get('https://new-shop.example/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('https://new-shop.example/sitemap.xml')->assertNotFound();
    }

    public function test_brand_identity_propagates_to_storefront_product_schema_admin_login_and_feed(): void
    {
        $seo = SeoSetting::current();
        $seo->update([
            'site_name' => 'Sinay Design',
            'title_suffix' => 'Sinay Design',
            'default_description' => 'Dekorasyon ürünleri ve seçkin tasarımlar.',
            'noindex' => false,
        ]);

        $category = Category::create([
            'name' => 'Dekoratif Ürünler', 'slug' => 'dekoratif-urunler',
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Seramik Vazo',
            'slug' => 'seramik-vazo',
            'sku' => 'SD-VAZO-001',
            'primary_category_id' => $category->id,
            'price' => '249.90',
            'stock_quantity' => 6,
            'is_active' => true,
            'is_demo' => false,
            'image_path' => 'storage/products/vazo.webp',
        ]);

        $this->assertSame('Sinay Design', StoreIdentity::name());
        $this->assertSame(['Sinay', 'Design'], StoreIdentity::wordmark());
        $this->assertSame('Diğer ürün seçenekleri', StoreIdentity::alternativesLabel($product));
        $weightProduct = clone $product;
        $weightProduct->name = 'Surf Kurşun 125 gr';
        $this->assertSame('Diğer gramaj seçenekleri', StoreIdentity::alternativesLabel($weightProduct));

        $home = $this->get('https://sinaydesign.com/')->assertOk()->getContent();
        $this->assertStringContainsString('Sinay Design', $home);
        $this->assertStringContainsString('Dekorasyon ürünleri', $home);
        $this->assertStringNotContainsString('Polaris Kurşun', $home);
        $this->assertStringNotContainsString('polariskursun', $home);
        $this->assertStringContainsString('#ui-shopping-bag', $home);
        $this->assertStringContainsString('https://sinaydesign.com/', $home);

        $html = $this->get('https://sinaydesign.com/urun/seramik-vazo')->assertOk()->getContent();
        $this->assertStringContainsString('Sinay Design', $html);
        $this->assertStringNotContainsString('Polaris Kurşun', $html);
        $this->assertStringContainsString('"name":"Sinay Design"', $html);
        $this->assertStringContainsString('"price":"249.90"', $html);
        $this->assertStringContainsString('https://sinaydesign.com/storage/products/vazo.webp', $html);

        $this->get('https://sinaydesign.com/admin/login')->assertOk()->assertSee('Sinay Design');

        $xml = $this->get('https://sinaydesign.com/google/merchant-products.xml')->assertOk()->streamedContent();
        $this->assertStringContainsString('Sinay Design Ürün Kataloğu', $xml);
        $this->assertStringNotContainsString('Polaris', $xml);
        $this->assertStringContainsString('seramik-vazo', $xml);

        $product->update(['google_brand' => 'Gerçek Üretici']);
        $this->assertSame('Gerçek Üretici', StoreIdentity::productBrand($product->fresh()));
    }

    public function test_new_installation_scripts_do_not_overwrite_other_sites(): void
    {
        $install = file_get_contents(base_path('scripts/install-production.sh'));
        $webroot = file_get_contents(base_path('scripts/prepare-hostinger-webroot.sh'));

        $this->assertStringContainsString('--check', $install);
        $this->assertStringContainsString('APP_DEBUG', $install);
        $this->assertStringContainsString('APP_KEY', $install);
        $this->assertStringContainsString('DB_DATABASE', $install);
        $this->assertStringContainsString('real checkout', strtolower($install));
        $this->assertStringNotContainsString('rm -rf', $install);

        $this->assertStringContainsString('already exists', $webroot);
        $this->assertStringContainsString('APP/public/index.php', $webroot);
        $this->assertStringNotContainsString('rm -rf', $webroot);
    }
}
