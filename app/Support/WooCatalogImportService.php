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
    public function __construct(private readonly WooProductMedia $media) {}

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
        if (! in_array($type, ['simple', 'variation'], true)) {
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
            if (! ctype_digit($id) || (int) $id <= 0) {
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
        if (! $regular && $sale) {
            $regular = $sale;
            $sale = null;
        }
        if (! $regular || $regular <= 0) {
            throw new RuntimeException('Bu ürünün geçerli pozitif satış fiyatı yok.');
        }
        $discount = $sale && $sale > 0 && $sale < $regular;
        $stockText = WooCsvReader::get($row, 'Stock');
        $tracksStock = $stockText !== '';
        if ($tracksStock && ! preg_match('/^\d+$/', $stockText)) {
            throw new RuntimeException('Stok miktarı geçerli bir tam sayı değil.');
        }
        // WooCommerce leaves Stock blank when product-level inventory tracking
        // is disabled. NEWTIC keeps that semantic with tracks_stock=false; 999
        // is only the existing per-order purchase ceiling, not a claimed count.
        $stock = $tracksStock ? min(100000000, (int) $stockText) : 999;
        $inStock = ! in_array(mb_strtolower(WooCsvReader::get($row, 'In stock?')), ['0', 'false', 'no', 'hayır'], true);
        if (! $inStock) {
            $stock = 0;
        }
        $published = in_array(WooCsvReader::get($row, 'Published'), ['1', 'true'], true);
        $visible = ! in_array(WooCsvReader::get($row, 'Visibility in catalog'), ['hidden', 'search'], true);
        $active = $published && $visible && $inStock && (! $tracksStock || $stock > 0);

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
        $externalThumbnail = WooCsvReader::get(
            $row,
            'Meta: _thumbnail_ext_url',
            'meta:_thumbnail_ext_url'
        ) ?: WooCsvReader::get($parent, 'Meta: _thumbnail_ext_url', 'meta:_thumbnail_ext_url');
        $images = $this->prependImage($images, $externalThumbnail);
        $seoTitleRaw = WooCsvReader::get($row,
            'Meta: _yoast_wpseo_title', 'meta:_yoast_wpseo_title',
            'Meta: rank_math_title', 'meta:rank_math_title')
            ?: WooCsvReader::get($parent,
                'Meta: _yoast_wpseo_title', 'meta:_yoast_wpseo_title',
                'Meta: rank_math_title', 'meta:rank_math_title');
        $seoTitle = $this->seoText($seoTitleRaw, $name);
        $seoDescription = WooCsvReader::get($row, 'Meta: _yoast_wpseo_metadesc', 'meta:_yoast_wpseo_metadesc',
            'Meta: rank_math_description', 'meta:rank_math_description')
            ?: WooCsvReader::get($parent, 'Meta: _yoast_wpseo_metadesc', 'meta:_yoast_wpseo_metadesc',
                'Meta: rank_math_description', 'meta:rank_math_description');
        $seoFocusKeyword = WooCsvReader::get($row, 'Meta: _yoast_wpseo_focuskw', 'meta:_yoast_wpseo_focuskw',
            'Meta: rank_math_focus_keyword', 'meta:rank_math_focus_keyword')
            ?: WooCsvReader::get($parent, 'Meta: _yoast_wpseo_focuskw', 'meta:_yoast_wpseo_focuskw',
                'Meta: rank_math_focus_keyword', 'meta:rank_math_focus_keyword');

        $processed = DB::transaction(function () use (
            $batch, $row, $parent, $type, $id, $sku, $sourceKey, $name,
            $regular, $sale, $discount, $stock, $tracksStock, $active, $categoryText,
            $description, $shortDescription, $seoTitle, $seoDescription, $seoFocusKeyword
        ) {
            $link = CatalogImportSource::query()
                ->where('source_site', $batch->source_site)
                ->where('source_key', $sourceKey)->lockForUpdate()->first();
            $product = $link ? Product::query()->lockForUpdate()->find($link->product_id) : null;
            if ($link && ! $product) {
                throw new RuntimeException('Ürünün önceki kaynak bağlantısı bozuk. Kontrol gerekli.');
            }
            if (! $product && Product::query()->where('sku', $sku)->exists()) {
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
            if (! $product) {
                $base = $slug ?: 'urun';
                $slug = $base;
                $suffix = 2;
                while (Product::query()->where('slug', $slug)->exists()) {
                    $slug = mb_substr($base, 0, 180).'-'.$suffix++;
                }
            }
            $shippingWeight = $this->decimal(WooCsvReader::get($row, 'Weight (kg)'));
            if ($shippingWeight === null) {
                $grams = $this->decimal(WooCsvReader::get($row, 'Weight (g)'));
                $shippingWeight = $grams === null ? null : $grams / 1000;
            }
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
                'tracks_stock' => $tracksStock,
                'weight_kg' => $shippingWeight,
                'dimensions_cm' => $dimensions,
                'seo_title' => mb_substr($seoTitle, 0, 255) ?: null,
                'seo_description' => mb_substr($seoDescription, 0, 2000) ?: null,
                'seo_focus_keyword' => mb_substr($seoFocusKeyword, 0, 255) ?: null,
                // A reimport without "publish" must not unpublish products
                // that the merchant manually activated after an earlier run.
                'is_active' => $batch->publish_products ? $active : ($product?->is_active ?? false),
                'is_demo' => false,
            ];
            if (! $product) {
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
        if (! $tracksStock) {
            $warnings[] = 'WooCommerce stok takibi kapalı; ürün NEWTIC\'te stok takibi olmadan aktarıldı.';
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
        if (! $paths) {
            $paths = ['Genel Ürünler'];
        }
        $related = [];
        $leafCategories = [];

        foreach ($paths as $hierarchy) {
            $parentId = null;
            foreach (array_filter(array_map('trim', explode('>', $hierarchy))) as $segment) {
                $name = mb_substr(html_entity_decode(strip_tags($segment), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 190);
                if (! $name) {
                    continue;
                }
                $category = Category::query()->where('name', $name)->where('parent_id', $parentId)->first();
                if (! $category) {
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
            if ($parentId) {
                $leafCategories[] = $category;
            }
        }
        // "Genel" is a secondary catch-all category in the real Polaris CSV.
        // Prefer the product-family leaf regardless of whether Woo put Genel
        // first or last in the comma-separated category list.
        $primaryCategory = collect($leafCategories)->first(function (Category $category) {
            return ! in_array(mb_strtolower($category->name), [
                'genel', 'uncategorized', 'kategorize edilmemiş',
            ], true);
        }) ?? ($leafCategories[0] ?? null);
        $primary = $primaryCategory?->id;
        if (! $primary) {
            throw new RuntimeException('Ürüne kategori tanımlanamadı.');
        }

        return [$primary, array_values($related)];
    }

    private function decimal(string $value): ?float
    {
        $raw = trim(str_replace(' ', '', $value));
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',') && ! str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }
        if (! preg_match('/^\d{1,10}(\.\d{1,3})?$/', $raw)) {
            return null;
        }

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
        if (trim($html) === '') {
            return null;
        }
        $html = str_replace(['\\r\\n', '\\n', '\\r', '\\t'], ["\n", "\n", "\n", "\t"], $html);
        $html = preg_replace('/\[\/?ozel_gramaj_tablosu\b[^\]]*\]/iu', '', $html) ?? $html;
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~</?(p|div|br|li|h[1-6])\b[^>]*>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return mb_substr(trim($text), 0, $max) ?: null;
    }

    private function seoText(string $template, string $productName): string
    {
        if (trim($template) === '') {
            return '';
        }
        $text = str_ireplace(
            ['%%title%%', '%%page%%', '%%sep%%', '%%sitename%%'],
            [$productName, '', '-', 'Polaris Kurşun'],
            $template
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function prependImage(string $images, string $featured): string
    {
        $urls = preg_split('/,\s*(?=https?:\/\/)/i', $images) ?: [];
        if ($featured !== '') {
            array_unshift($urls, $featured);
        }
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));

        return implode(', ', $urls);
    }
}
