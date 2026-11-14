<?php

namespace Tests\Feature;

use App\Models\CatalogImport;
use App\Models\CatalogImportSource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WooCommerceProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function csv(array $rows): string
    {
        $head = [
            'ID', 'Type', 'SKU', 'Name', 'Published', 'In stock?', 'Stock',
            'Regular price', 'Sale price', 'Categories', 'Description',
            'Short description', 'Images', 'Parent', 'Attribute 1 name',
            'Attribute 1 value(s)', 'Weight (kg)', 'Meta: _yoast_wpseo_title',
            'Meta: _yoast_wpseo_metadesc', 'Meta: _yoast_wpseo_focuskw',
        ];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $head, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_replace(array_fill(0, count($head), ''), $row), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function rows(): array
    {
        return [
            ['100', 'variable', '', 'Fırdöndülü Surf Kurşun', '1', '1', '', '', '', 'Kurşunlar > Surf Kurşun',
                '<p>Ana ürün <strong>tanıtımı</strong>.</p>', '<p>Kurşun ürünü</p>', '', '', '', '', '',
                'Surf Kurşun SEO', 'Surf kurşun açıklaması', 'surf kurşun'],
            ['101', 'variation', 'SURF-75', '', '1', '1', '5', '70', '', '', '', '', '', 'id:100', 'Gramaj', '75 gr', '0.075', ''],
            ['102', 'variation', 'SURF-100', '', '1', '1', '7', '100', '', '', '', '', '', 'id:100', 'Gramaj', '100 gr', '0.100', ''],
            ['200', 'simple', 'JIG-55', 'Jig Kurşun', '1', '1', '8', '60', '50', 'Kurşunlar > Jig', '<p>İlk satır.</p><p>İkinci satır.</p>',
                'Kısa tanıtım', '', '', '', '', '0.055', 'Jig Kurşun SEO'],
        ];
    }

    private function upload(array $rows): CatalogImport
    {
        $this->post(route('admin.product-imports.upload'), [
            'csv' => UploadedFile::fake()->createWithContent('wc-product-export.csv', $this->csv($rows)),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return CatalogImport::latest('id')->firstOrFail();
    }

    private function complete(CatalogImport $import, bool $publish = true): void
    {
        $this->post(route('admin.product-imports.start', $import), [
            'publish_products' => $publish ? 1 : 0,
        ])->assertRedirect();

        for ($n = 0; $n < 20 && $import->fresh()->status !== 'completed'; $n++) {
            $this->postJson(route('admin.product-imports.step', $import))->assertOk();
        }
        $this->assertSame('completed', $import->fresh()->status);
    }

    public function test_product_import_requires_admin(): void
    {
        $this->get(route('admin.product-imports.index'))->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(route('admin.product-imports.index'))->assertForbidden();
        $this->post(route('admin.product-imports.upload'))->assertForbidden();
    }

    public function test_preview_does_not_touch_catalog_and_maintains_full_original_rows(): void
    {
        Storage::fake('local');
        $this->loginAdmin();
        $import = $this->upload($this->rows());

        $this->assertSame('preview', $import->status);
        $this->assertSame(4, $import->total_rows);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->get(route('admin.product-imports.show', $import))->assertOk()
            ->assertSee('Aktarım önizlemesi')->assertSee('SURF-75')
            ->assertSee('Varyasyonlu ana ürün satırları');
        $this->assertSame('<p>Ana ürün <strong>tanıtımı</strong>.</p>',
            $import->rows()->where('woo_id', '100')->firstOrFail()->payload['description']);
    }

    public function test_variations_remain_separate_products_and_details_are_imported(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->loginAdmin();
        $import = $this->upload($this->rows());
        $this->complete($import);

        $this->assertDatabaseCount('products', 3);
        $this->assertSame(3, $import->fresh()->created_rows);
        $this->assertSame(1, $import->fresh()->skipped_rows);
        $this->assertSame(0, $import->fresh()->failed_rows);
        $this->assertDatabaseCount('catalog_import_sources', 3);

        $surf75 = Product::query()->where('sku', 'SURF-75')->firstOrFail();
        $surf100 = Product::query()->where('sku', 'SURF-100')->firstOrFail();
        $jig = Product::query()->where('sku', 'JIG-55')->firstOrFail();

        $this->assertNotSame($surf75->id, $surf100->id);
        $this->assertNotSame($surf75->slug, $surf100->slug);
        $this->assertStringContainsString('75 gr', $surf75->name);
        $this->assertStringContainsString('100 gr', $surf100->name);
        $this->assertSame(5, $surf75->stock_quantity);
        $this->assertSame(7, $surf100->stock_quantity);
        $this->assertSame('70.00', $surf75->price);
        $this->assertTrue($surf75->is_active);
        $this->assertSame('50.00', $jig->price);
        $this->assertSame('60.00', $jig->compare_at_price);
        $this->assertSame('Jig Kurşun SEO', $jig->seo_title);
        $this->assertSame('Surf Kurşun SEO', $surf75->seo_title);
        $this->assertSame('Surf kurşun açıklaması', $surf75->seo_description);
        $this->assertSame('surf kurşun', $surf75->seo_focus_keyword);
        $this->assertStringContainsString('İkinci satır.', $jig->description);
        $this->assertSame('Kısa tanıtım', $jig->short_description);
        $this->assertSame('Kurşunlar', $surf75->category->parent->name);
        $this->assertGreaterThanOrEqual(2, $surf75->categories()->count());

        $source = CatalogImportSource::where('source_key', 'id:101')->firstOrFail();
        $this->assertSame('variation', $source->original_data['type']);
        $this->assertSame('id:100', $source->original_data['product']['parent']);
        $this->get(route('product.show', $surf75->slug))->assertOk();
    }

    public function test_reimport_updates_matched_products_without_duplicating(): void
    {
        Storage::fake('local');
        $this->loginAdmin();
        $batch1 = $this->upload($this->rows());
        $this->complete($batch1);

        $batch2 = $this->upload($this->rows());
        $this->complete($batch2);
        $this->assertDatabaseCount('products', 3);
        $this->assertSame(0, $batch2->fresh()->created_rows);
        $this->assertSame(3, $batch2->fresh()->updated_rows);
        $this->assertSame(1, $batch2->fresh()->skipped_rows);
        $this->assertSame(0, $batch2->fresh()->failed_rows);
    }

    public function test_existing_manual_sku_is_not_overwritten(): void
    {
        Storage::fake('local');
        $this->loginAdmin();
        $category = Category::create([
            'name' => 'Elle oluşturuldu', 'slug' => 'manuel', 'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'MANUAL-1', 'name' => 'Manuel Ürün', 'slug' => 'manuel-urun',
            'price' => '999.00', 'stock_quantity' => 19, 'is_active' => true,
        ]);
        $row = ['300', 'simple', 'MANUAL-1', 'Farklı Woo Ürünü', '1', '1', '5', '25', '', 'Kurşunlar'];
        $batch = $this->upload([$row]);
        $this->complete($batch);

        $this->assertSame(1, $batch->fresh()->failed_rows);
        $this->assertSame('999.00', $product->fresh()->price);
        $this->assertSame(19, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_new_products_are_inactive_unless_explicitly_approved(): void
    {
        Storage::fake('local');
        $this->loginAdmin();
        $batch = $this->upload([$this->rows()[3]]);
        $this->complete($batch, false);
        $this->assertFalse(Product::firstOrFail()->is_active);
    }

    public function test_unsafe_remote_images_are_not_downloaded_or_followed(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Http::fake();
        $this->loginAdmin();
        $row = ['400', 'simple', 'MEDIA-4', 'Medya Ürün', '1', '1', '5', '49.90', '', 'Kurşunlar', '', '',
            'https://127.0.0.1/wp-content/uploads/secret.png'];
        $batch = $this->upload([$row]);
        $this->complete($batch);

        $this->assertSame(1, $batch->fresh()->created_rows);
        $message = $batch->rows()->firstOrFail()->message;
        $this->assertStringContainsString('yalnızca eski wordpress', mb_strtolower($message));
        Http::assertNothingSent();
    }

    public function test_turkish_export_preserves_untracked_stock_media_categories_weight_and_seo(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Http::fake([
            'https://polariskursun.com/wp-content/uploads/*' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);
        $this->loginAdmin();

        $headers = [
            'Kimlik', 'Tür', 'Stok kodu (SKU)', 'İsim', 'Yayımlanmış',
            'Katalogda görünürlük', 'Stokta?', 'Stok', 'Normal fiyat',
            'Kategoriler', 'Açıklama', 'Kısa açıklama', 'Görseller',
            'Ağırlık (g)', 'Meta: _yoast_wpseo_title', 'Meta: _yoast_wpseo_metadesc',
            'Meta: _yoast_wpseo_focuskw',
            'Meta: _thumbnail_ext_url',
        ];
        $values = [
            '4451', 'simple', 'FSK75', '75 Gr. Polaris Klasik Fosforlu Surf Kurşun', '1',
            'visible', '1', '', '69.90', 'Genel, Polaris Klasik Fosforlu Surf Kurşun',
            '<p>Birinci satır.</p>\\n<p>İkinci satır.</p>',
            'Kısa tanıtım\\n[ozel_gramaj_tablosu kategori="fosforlu"]',
            'https://polariskursun.com/wp-content/uploads/2024/10/a.png, https://polariskursun.com/wp-content/uploads/2024/10/b.png',
            '150', '%%title%% %%page%% %%sep%% %%sitename%%', 'Fosforlu kurşun açıklaması',
            'fosforlu surf kurşun',
            'https://polariskursun.com/wp-content/uploads/2024/10/featured.png',
        ];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        fputcsv($stream, $values, ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->post(route('admin.product-imports.upload'), [
            'csv' => UploadedFile::fake()->createWithContent('urunler.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $import = CatalogImport::latest('id')->firstOrFail();
        $this->complete($import);

        $product = Product::query()->where('sku', 'FSK75')->firstOrFail();
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->tracks_stock);
        $this->assertSame(999, $product->stock_quantity);
        $this->assertSame('0.150', $product->weight_kg);
        $this->assertSame('Polaris Klasik Fosforlu Surf Kurşun', $product->category->name);
        $this->assertSame(2, $product->categories()->count());
        $this->assertSame(
            '75 Gr. Polaris Klasik Fosforlu Surf Kurşun - Polaris Kurşun',
            $product->seo_title
        );
        $this->assertSame('Fosforlu kurşun açıklaması', $product->seo_description);
        $this->assertSame('fosforlu surf kurşun', $product->seo_focus_keyword);
        $this->assertMatchesRegularExpression('/Birinci satır\.\s+İkinci satır\./u', $product->description);
        $this->assertStringNotContainsString('ozel_gramaj_tablosu', $product->short_description);
        $this->assertSame(3, $product->images()->count());
        $this->assertStringContainsString(hash('sha256', 'https://polariskursun.com/wp-content/uploads/2024/10/featured.png'), $product->image_path);
        $this->assertStringContainsString('stok takibi kapalı', mb_strtolower($import->rows()->firstOrFail()->message));
        $this->assertSame('4451', CatalogImportSource::firstOrFail()->original_data['product']['id']);
    }

    public function test_invalid_csv_headers_do_not_create_import_or_products(): void
    {
        Storage::fake('local');
        $this->loginAdmin();
        $this->post(route('admin.product-imports.upload'), [
            'csv' => UploadedFile::fake()->createWithContent('bad.csv', "foo,bar\n1,2\n"),
        ])->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('catalog_imports', 0);
        $this->assertDatabaseCount('products', 0);
    }
}
