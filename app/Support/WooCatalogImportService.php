<?php

namespace App\Support;

use App\Models\CatalogImport;
use App\Models\CatalogImportRow;
use App\Models\CatalogImportSource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class WooCatalogImportService
{
    public function __construct(private readonly WooProductMedia $media)
    {
    }

    /**
     * @return array{status:string,product_id:?int,message:string}
     */
    public function apply(CatalogImport $batch, CatalogImportRow $entry): array
    {
        $row = $entry->payload;
        $type = WooCsvReader::type($row);
        if ($type === 'variable') {
            return [
                'status' => 'skipped', 'product_id' => null,
                'message' => 'Varyasyonlu ana ürün atlandı; her varyasyon ayrı SKU olarak aktarılacak.',
            ];
        }
        if (!in_array($type, ['simple', 'variation'], true)) {
            return [
                'status' => 'skipped', 'product_id' => null,
                'message' => 'Bu WooCommerce ürün tipi henüz desteklenmiyor: '.$type,
            ];
        }

        $parent = [];
        if ($type === 'variation') {
            $parentToken = WooCsvReader::get($row, 'Parent');
            $parentId = preg_replace('/^id:/i', '', $parentToken);
            if ($parentId !== '') {
                $parentRow = $batch->rows()
                    ->where('woo_id', $parentId)
                    ->where('row_type', 'variable')->first();
                if ($parentRow) {
                    $parent = $parentRow->payload;
                }
            }
        }

        $id = WooCsvReader::get($row, 'ID');
        $sku = mb_substr(WooCsvReader::get($row, 'SKU'), 0, 100);
        if ($sku === '') {
            if (!ctype_digit($id) || (int) $id <= 0) {
                throw new RuntimeException('Ürün ID veya SKU eksik; güvenli tekrar aktarım için gerekli.');
            }
            $sku = 'WC-'.$id;
        }
        $sourceKey = ctype_digit($id) && (int) $id > 0 ? 'id:'.$id : 'sku:'.$sku;
        $rawName = WooCsvReader::get($row, 'Name');
        $parentName = WooCsvReader::get($parent, 'Name');
        $name = $rawName ?: $parentName;
        if ($name === '') {
            throw new RuntimeException('Ürünün adı boş.');
        }

        // Woo exports sometimes omit variation labels. Never collapse different
        // gram/weight choices into a single NEWTIC product.
        if ($type === 'variation') {
            $traits = [];
            for ($i = 1; $i <= 12; $i++) {
                $key = WooCsvReader::get($row, "Attribute {$i} value(s)");
                if ($key !== '') {
                    $traits[] = $key;
                }
            }
            if ($traits && ($rawName === '' || mb_strtolower($rawName) === mb_strtolower($parentName))) {
                $name .= ' - '.implode(' / ', $traits);
            }
        }
        $name = mb_substr(html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 190);
        if ($name === '') {
            throw new RuntimeException('Geçerli bir ürün adı bulunamadı.');
        }

        $regular = $this->money(WooCsvReader::get($row, 'Regular price'));
        $sale = $this->money(WooCsvReader::get($row, 'Sale price'));
        if (!$regular && $sale) {
            $regular = $sale;
            $sale = null;
        }
        if (!$regular || $regular <= 0) {
            throw new RuntimeException('Bu ürünün geçerli pozitif satış fiyatı yok.');
        }
        $discount = $sale && $sale > 0 && $sale < $regular;
        $stockText = WooCsvReader::get($row, 'Stock');
        $stockUnknown = $stockText === '';
        $stock = !$stockUnknown && preg_match('/^\d+$/', $stockText)
            ? min(100000000, (int) $stockText) : 0;
        if (WooCsvReader::get($row, 'In stock?') === '0') {
            $stock = 0;
        }
        $published = in_array(WooCsvReader::get($row, 'Published'), ['1', 'true'], true);
        $visible = !in_array(WooCsvReader::get($row, 'Visibility in catalog'), ['hidden', 'search'], true);
        $active = $published && $visible && $stock > 0;

        $categoryText = WooCsvReader::get($row, 'Categories')
            ?: WooCsvReader::get($parent, 'Categories');
        $description = $this->plainText(
            WooCsvReader::get($row, 'Description')
            ?: WooCsvReader::get($parent, 'Description'), 10000
        );
        $shortDescription = $this->plainText(
            WooCsvReader::get($row, 'Short description')
            ?: WooCsvReader::get($parent, 'Short description'), 10000
        );
        $images = WooCsvReader::get($row, 'Images') ?: WooCsvReader::get($parent, 'Images');
        $seoTitle = WooCsvReader::get($row, 'Meta: _yoast_wpseo_title', 'meta:_yoast_wpseo_title',
            'Meta: rank_math_title', 'meta:rank_math_title');
        $seoDescription = WooCsvReader::get($row, 'Meta: _yoast_wpseo_metadesc', 'meta:_yoast_wpseo_metadesc',
            'Meta: rank_math_description', 'meta:rank_math_description');

        $processed = DB::transaction(function () use (
            $batch, $entry, $row, $parent, $type, $id, $sku, $sourceKey, $name,
            $regular, $sale, $discount, $stock, $active, $categoryText,
            $description, $shortDescription, $seoTitle, $seoDescription
        ) {
            $link = CatalogImportSource::query()
                ->where('source_site', $batch->source_site)
                ->where('source_key', $sourceKey)->lockForUpdate()->first();
            $product = $link ? Product::query()->lockForUpdate()->find($link->product_id) : null;
            if ($link && !$product) {
                throw new RuntimeException('Ürünün önceki kaynak bağlantısı bozuk. Kontrol gerekli.');
            }
            if (!$product && Product::query()->where('sku', $sku)->exists()) {
                throw new RuntimeException('SKU mevcut bir NEWTIC ürününde kullanılıyor: '.$sku.'. Otomatik olarak üzerine yazılmadı.');
            }
            if ($product && $sku !== $product->sku &&
                Product::query()->where('sku', $sku)->where('id', '!=', $product->id)->exists()) {
                throw new RuntimeException('Yeni SKU başka ürüne ait: '.$sku);
            }
            [$categoryId, $categoryIds] = $this->categories($categoryText);
            $slug = WooCsvReader::get($row, 'Slug');
            $slug = Str::slug($slug ?: $name, '-', 'tr') ?: Str::slug($name, '-', 'tr');
            $slug = mb_substr($slug, 0, 190);
            if (!$product) {
                $base = $slug ?: 'urun';
                $slug = $base;
                $suffix = 2;
                while (Product::query()->where('slug', $slug)->exists()) {
                    $slug = mb_substr($base, 0, 180).'-'.$suffix++;
                }
            }
            $shippingWeight = $this->decimal(WooCsvReader::get($row, 'Weight (kg)'));
            $dimensions = [];
            foreach (['length' => 'Length (cm)', 'width' => 'Width (cm)', 'height' => 'Height (cm)'] as $field => $column) {
                $dimensions[$field] = $this->decimal(WooCsvReader::get($row, $column));
            }

            $properties = [
                'primary_category_id' => $categoryId,
                'sku' => $sku, 'name' => $name,
                'description' => $description,
                'short_description' => $shortDescription,
                'price' => $discount ? $sale : $regular,
                'compare_at_price' => $discount ? $regular : null,
                'stock_quantity' => $stock,
                'weight_kg' => $shippingWeight,
                'dimensions_cm' => $dimensions,
                'seo_title' => mb_substr($seoTitle, 0, 255) ?: null,
                'seo_description' => mb_substr($seoDescription, 0, 2000) ?: null,
                // A reimport without "publish" must not unpublish products
                // that the merchant manually activated after an earlier run.
                'is_active' => $batch->publish_products ? $active : ($product?->is_active ?? false),
                'is_demo' => false,
            ];
            if (!$product) {
                $properties['slug'] = $slug;
                $product = Product::create($properties);
                $kind = 'created';
            } else {
                $product->update($properties);
                $kind = 'updated';
            }
            $product->categories()->sync($categoryIds);
            CatalogImportSource::updateOrCreate([
                'source_site' => $batch->source_site,
                'source_key' => $sourceKey,
            ], [
                'product_id' => $product->id,
                'source_permalink' => mb_substr(WooCsvReader::get($row, 'Permalink'), 0, 1000) ?: null,
                'original_data' => [
                    'product' => $row,
                    'parent' => $parent ?: null,
                    'type' => $type,
                    'woo_id' => $id,
                ],
                'last_imported_at' => now(),
            ]);
            return [$product, $kind];
        });

        [$product, $kind] = $processed;
        $warnings = [];
        if ($stockUnknown) {
            $warnings[] = 'WooCommerce stok miktarı boş; güvenlik için stok 0 olarak aktarıldı.';
        }
        $warnings = array_merge($warnings, $this->media->sync($product, $images));

        return [
            'status' => $kind,
            'product_id' => $product->id,
            'message' => $warnings ? implode(' | ', $warnings) : 'Aktarım başarılı.',
        ];
    }

    private function categories(string $csv): array
    {
        $paths = array_filter(array_map('trim', explode(',', $csv)));
        if (!$paths) {
            $paths = ['Genel Ürünler'];
        }
        $related = [];
        $primary = null;

        foreach ($paths as $hierarchy) {
            $parentId = null;
            foreach (array_filter(array_map('trim', explode('>', $hierarchy))) as $segment) {
                $name = mb_substr(html_entity_decode(strip_tags($segment), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 190);
                if (!$name) continue;
                $category = Category::query()->where('name', $name)->where('parent_id', $parentId)->first();
                if (!$category) {
                    $category = Category::create([
                        'name' => $name,
                        'slug' => UniqueSlug::make(Category::class, $name),
                        'parent_id' => $parentId,
                        'is_active' => true,
                        'sort_order' => 0,
                    ]);
                }
                $parentId = $category->id;
                $related[$category->id] = $category->id;
            }
            $primary ??= $parentId;
        }
        if (!$primary) {
            throw new RuntimeException('Ürüne kategori tanımlanamadı.');
        }
        return [$primary, array_values($related)];
    }

    private function decimal(string $value): ?float
    {
        $raw = trim(str_replace(' ', '', $value));
        if ($raw === '') return null;
        if (str_contains($raw, ',') && !str_contains($raw, '.')) $raw = str_replace(',', '.', $raw);
        if (!preg_match('/^\d{1,10}(\.\d{1,3})?$/', $raw)) return null;
        return (float) $raw;
    }

    private function money(string $value): ?string
    {
        $amount = $this->decimal($value);
        return $amount !== null && $amount <= 9999999999.99
            ? number_format($amount, 2, '.', '') : null;
    }

    private function plainText(string $html, int $max): ?string
    {
        if (trim($html) === '') return null;
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~</?(p|div|br|li|h[1-6])\b[^>]*>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return mb_substr(trim($text), 0, $max) ?: null;
    }
}
