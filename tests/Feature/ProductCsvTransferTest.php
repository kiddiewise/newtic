<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBadge;
use App\Models\User;
use App\Support\ProductCsvTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCsvTransferTest extends TestCase
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
        return Category::create(['name' => 'Surf Kurşun', 'slug' => 'surf-kursun', 'is_active' => true]);
    }

    private function row(array $overrides = []): array
    {
        return array_replace(array_fill_keys(ProductCsvTransfer::HEADERS, ''), array_merge([
            'sku' => 'POL-125',
            'urun_adi' => 'Polaris 125 gr',
            'kategori_slug' => 'surf-kursun',
            'normal_fiyat' => '129,90',
            'stok' => '5',
            'stok_takibi' => '1',
            'yayinda' => '1',
        ], $overrides));
    }

    private function csv(array $rows, ?array $header = null): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        $header ??= ProductCsvTransfer::HEADERS;
        fputcsv($stream, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($field) => $row[$field] ?? '', $header), ';', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $content;
    }

    private function upload(string $content)
    {
        return $this->post(route('admin.products.bulk.upload'), [
            'csv' => UploadedFile::fake()->createWithContent('urunler.csv', $content),
        ]);
    }

    public function test_only_administrators_can_download_and_bulk_import_products(): void
    {
        $this->get(route('admin.products.bulk.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.products.bulk.export'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.products.bulk.index'))->assertForbidden();
        $this->upload($this->csv([$this->row()]))->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_download_excel_friendly_template_and_all_products(): void
    {
        $category = $this->category();
        $product = Product::create([
            'sku' => 'POL-125', 'slug' => 'polaris-125',
            'name' => 'Polaris 125 gr', 'primary_category_id' => $category->id,
            'price' => '99.90', 'compare_at_price' => '129.90',
            'stock_quantity' => 8, 'is_active' => true,
        ]);
        $product->options()->create(['value' => '0,25']);
        $product->badges()->sync([ProductBadge::where('name', 'Popüler Ürün')->firstOrFail()->id]);
        $this->actingAs($this->admin());

        $template = $this->get(route('admin.products.bulk.template'))->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBFsku;urun_adi;kategori_slug", $template->streamedContent());
        $this->assertSame(1, substr_count($template->streamedContent(), "\n"));

        $download = $this->get(route('admin.products.bulk.export'))->assertOk();
        $content = $download->streamedContent();
        $this->assertStringContainsString('text/csv', $download->headers->get('content-type'));
        $this->assertStringContainsString('POL-125', $content);
        $this->assertStringContainsString('129.90;99.90;8;1;1;', $content);
        $this->assertStringContainsString('Popüler Ürün', $content);
        $this->assertStringContainsString('0,25', $content);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Toplu ürün yükle');
    }

    public function test_bulk_upload_creates_and_updates_by_sku_preserving_existing_url_and_separate_grammages(): void
    {
        $category = $this->category();
        $product = Product::create([
            'sku' => 'POL-125', 'slug' => 'ozel-adres-korunacak',
            'name' => 'Eski isim', 'primary_category_id' => $category->id,
            'price' => '100.00', 'stock_quantity' => 12,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin());
        $this->upload($this->csv([
            $this->row([
                'urun_adi' => 'Yeni Ürün 125 gr',
                'normal_fiyat' => '159,90',
                'indirimli_fiyat' => '119,90',
                'stok' => '4',
                'rozetler' => 'Popüler Ürün|Hızlı Teslimat',
                'secenek_basligi' => 'İğne kalınlığı',
                'secenekler' => '0,25|0,30',
                'seo_baslik' => 'Yeni SEO Başlığı',
                'agirlik_kg' => '0,125',
            ]),
            $this->row([
                'sku' => 'POL-150', 'urun_adi' => 'Yeni Ürün 150 gr',
                'normal_fiyat' => '169,90',
                'stok' => '20',
                'yayinda' => '0',
            ]),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('products', 2);
        $original = $product->fresh();
        $this->assertSame('ozel-adres-korunacak', $original->slug);
        $this->assertSame('119.90', $original->price);
        $this->assertSame('159.90', $original->compare_at_price);
        $this->assertSame(4, $original->stock_quantity);
        $this->assertSame('0.125', $original->weight_kg);
        $this->assertSame(2, $original->options()->count());
        $this->assertSame(2, $original->badges()->count());
        $this->assertSame('Yeni SEO Başlığı', $original->seo_title);
        $new = Product::where('sku', 'POL-150')->firstOrFail();
        $this->assertNotSame($new->slug, $original->slug);
        $this->assertFalse($new->is_active);

        // A repeat upload is idempotent: it updates, never duplicates products.
        $this->upload($this->csv([$this->row([
            'sku' => 'POL-150', 'urun_adi' => 'Yeni Ürün 150 gr',
            'normal_fiyat' => '169,90', 'stok' => '20', 'yayinda' => '0',
        ])]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
    }

    public function test_invalid_row_or_repeated_sku_aborts_entire_batch(): void
    {
        $this->category();
        $this->actingAs($this->admin());

        $this->upload($this->csv([
            $this->row(),
            $this->row(['sku' => 'POL-150', 'kategori_slug' => 'bulunamayan-kategori']),
        ]))->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('products', 0);

        $this->upload($this->csv([$this->row(), $this->row()]))
            ->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('products', 0);

        $this->upload($this->csv([$this->row(['normal_fiyat' => '=2+3'])]))
            ->assertSessionHasErrors('csv');
    }

    public function test_image_paths_are_server_local_and_never_delete_existing_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/photo.webp', 'sample-image');
        $this->category();
        $this->actingAs($this->admin());

        $this->upload($this->csv([$this->row([
            'kapak_gorseli' => 'storage/products/photo.webp',
            'galeri_gorselleri' => 'storage/products/photo.webp',
        ])]))->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertSame('storage/products/photo.webp', $product->image_path);
        $this->assertSame(1, $product->images()->count());

        // An empty image cell does not remove previously uploaded photographs.
        $this->upload($this->csv([$this->row()]))->assertSessionHasNoErrors();
        $this->assertSame(1, $product->images()->count());
        $this->assertSame('storage/products/photo.webp', $product->fresh()->image_path);

        $this->upload($this->csv([$this->row(['kapak_gorseli' => 'https://example.com/evil.png'])]))
            ->assertSessionHasErrors('csv');
        $this->assertSame(1, $product->images()->count());
    }

    public function test_utf8_turkish_text_and_quoted_multiline_content_survive_import(): void
    {
        $this->category();
        $this->actingAs($this->admin());
        $description = "Fosforlu çupra kurşunu;\nDenizde kullanılır, güvenilir.";
        $this->upload($this->csv([$this->row([
            'urun_adi' => 'İzmir İğneli Çupra Kurşunu',
            'aciklama' => $description,
            'seo_odak_kelime' => 'çupra kurşunu',
        ])]))->assertSessionHasNoErrors();

        $this->assertSame($description, Product::firstOrFail()->description);
        $this->assertSame('İzmir İğneli Çupra Kurşunu', Product::firstOrFail()->name);
    }

    public function test_export_escapes_spreadsheet_formula_values(): void
    {
        $category = $this->category();
        Product::create([
            'sku' => '=SUM(1,1)', 'slug' => 'formula-escape',
            'name' => '=HYPERLINK("https://example.com")', 'primary_category_id' => $category->id,
            'price' => '10.00', 'stock_quantity' => 2, 'is_active' => true,
        ]);
        $csv = $this->actingAs($this->admin())
            ->get(route('admin.products.bulk.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
