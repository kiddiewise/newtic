<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ProductCsvTransfer
{
    /** Excel in Turkish locale recognizes UTF-8 BOM and semicolon-delimited CSV. */
    public const HEADERS = [
        'sku', 'urun_adi', 'kategori_slug', 'normal_fiyat', 'indirimli_fiyat',
        'stok', 'stok_takibi', 'yayinda', 'aciklama', 'kisa_aciklama',
        'agirlik_kg', 'seo_baslik', 'seo_aciklama', 'seo_odak_kelime',
        'secenek_basligi', 'secenekler', 'rozetler', 'kapak_gorseli',
        'galeri_gorselleri', 'ek_kategori_sluglari',
    ];

    private const REQUIRED = [
        'sku', 'urun_adi', 'kategori_slug', 'normal_fiyat', 'stok', 'stok_takibi', 'yayinda',
    ];

    public const MAX_ROWS = 500;

    public function export($stream, bool $template = false): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, ';', '"', '');
        if ($template) {
            return;
        }

        Product::query()
            ->with(['category', 'categories', 'options', 'badges', 'images'])
            ->orderBy('id')
            ->chunkById(150, function ($products) use ($stream) {
                foreach ($products as $product) {
                    $regular = $product->compare_at_price ?: $product->price;
                    $row = [
                        $product->sku,
                        $product->name,
                        $product->category?->slug ?? '',
                        $regular,
                        $product->compare_at_price ? $product->price : '',
                        $product->stock_quantity,
                        $product->tracks_stock ? '1' : '0',
                        $product->is_active ? '1' : '0',
                        $product->description ?? '',
                        $product->short_description ?? '',
                        $product->weight_kg ?? '',
                        $product->seo_title ?? '',
                        $product->seo_description ?? '',
                        $product->seo_focus_keyword ?? '',
                        $product->option_label ?? '',
                        $product->options->pluck('value')->implode('|'),
                        $product->badges->pluck('name')->implode('|'),
                        $product->image_path ?? '',
                        $product->images->pluck('path')->implode('|'),
                        $product->categories->filter(fn ($category) => $category->id !== $product->primary_category_id)
                            ->pluck('slug')->implode('|'),
                    ];
                    fputcsv($stream, array_map(self::safeForExcel(...), $row), ';', '"', '');
                }
            });
    }

    /** @return array{created:int,updated:int,total:int} */
    public function import(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw ValidationException::withMessages(['csv' => 'Dosya okunamadı. Yeniden yükleyin.']);
        }

        try {
            $header = fgetcsv($handle, 0, ';', '"', '');
            $line = 1;
            if (is_array($header) && isset($header[0]) && str_starts_with((string) $header[0], "\xEF\xBB\xBF")) {
                $header[0] = substr($header[0], 3);
            }
            // Support Excel CSV files saved with an explicit separator directive.
            if (is_array($header) && ($header[0] ?? '') === 'sep=') {
                $header = fgetcsv($handle, 0, ';', '"', '');
                $line++;
            }
            if (!is_array($header)) {
                throw ValidationException::withMessages(['csv' => 'CSV başlık satırı bulunamadı.']);
            }
            $header = array_map(fn ($value) => trim((string) $value), $header);
            $requiredMissing = array_diff(self::REQUIRED, $header);
            $unknown = array_diff($header, self::HEADERS);
            if ($requiredMissing || $unknown || count(array_unique($header)) !== count($header)) {
                throw ValidationException::withMessages(['csv' =>
                    'CSV sütunları geçersiz. Eksik: '.implode(', ', $requiredMissing).
                    '. Tanınmayan: '.implode(', ', $unknown).'. NEWTIC şablonunu kullanın.'
                ]);
            }

            $categories = Category::query()->get()->keyBy('slug');
            $badges = ProductBadge::query()->get()->keyBy(fn (ProductBadge $badge) => mb_strtolower($badge->name));
            $rows = [];
            $seen = [];
            $errors = [];

            while (($cells = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                ++$line;
                if ($cells === [null] || collect($cells)->every(fn ($value) => trim((string) $value) === '')) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['csv' =>
                        'Tek seferde en fazla '.self::MAX_ROWS.' ürün yükleyebilirsiniz. Dosyayı bölerek tekrar deneyin.'
                    ]);
                }
                if (count($cells) !== count($header) || !mb_check_encoding(implode('', $cells), 'UTF-8')) {
                    $errors[] = $line.'. satır: Sütun sayısı veya UTF-8 karakter kodlaması geçersiz.';
                    continue;
                }
                $values = array_combine($header, array_map(self::decodeExcelCell(...), $cells));
                try {
                    $row = $this->parseRow($values, $categories, $badges);
                    $key = mb_strtolower($row['sku']);
                    if (isset($seen[$key])) {
                        throw new \InvalidArgumentException('Aynı SKU dosyada tekrar ediyor ('.$seen[$key].'. satır).');
                    }
                    $seen[$key] = $line;
                    $rows[] = $row;
                } catch (\InvalidArgumentException $error) {
                    $errors[] = $line.'. satır: '.$error->getMessage();
                }
                if (count($errors) > 25) {
                    break;
                }
            }

            if ($errors) {
                throw ValidationException::withMessages(['csv' =>
                    'Hiçbir ürün değiştirilmedi. '.implode(' ', array_slice($errors, 0, 20)).
                    (count($errors) > 20 ? ' Diğer hataları da düzelttikten sonra yeniden deneyin.' : '')
                ]);
            }
            if (!$rows) {
                throw ValidationException::withMessages(['csv' => 'CSV dosyasında aktarılacak ürün bulunamadı.']);
            }

            return DB::transaction(function () use ($rows) {
                $created = 0;
                $updated = 0;

                foreach ($rows as $row) {
                    $product = Product::query()->where('sku', $row['sku'])->lockForUpdate()->first();
                    if ($product) {
                        $product->update($row['attributes']);
                        $updated++;
                    } else {
                        $product = Product::create(array_merge($row['attributes'], [
                            'sku' => $row['sku'],
                            'slug' => UniqueSlug::make(Product::class, $row['attributes']['name']),
                            'is_demo' => false,
                        ]));
                        $created++;
                    }

                    if ($row['options'] !== null) {
                        foreach ($row['options'] as $value) {
                            $product->options()->firstOrCreate(['value' => $value]);
                        }
                        $product->options()->whereNotIn('value', $row['options'])->delete();
                    }
                    if ($row['badges'] !== null) {
                        $product->badges()->sync($row['badges']);
                    }
                    if ($row['categories'] !== null) {
                        $product->categories()->sync($row['categories']);
                    }
                    $this->applyImages($product, $row['cover'], $row['gallery']);
                }

                return ['created' => $created, 'updated' => $updated, 'total' => count($rows)];
            }, 3);
        } finally {
            fclose($handle);
        }
    }

    private function parseRow(array $values, $categories, $badges): array
    {
        $sku = trim($values['sku'] ?? '');
        $name = trim($values['urun_adi'] ?? '');
        $categorySlug = trim($values['kategori_slug'] ?? '');
        if ($sku === '' || mb_strlen($sku) > 100 || $name === '' || mb_strlen($name) > 190) {
            throw new \InvalidArgumentException('SKU ve ürün adı zorunlu (SKU en fazla 100, ad en fazla 190 karakter).');
        }
        $category = $categories->get($categorySlug);
        if (!$category) {
            throw new \InvalidArgumentException('Kategori slug bulunamadı: '.$categorySlug);
        }

        $regular = $this->price($values['normal_fiyat'] ?? '', 'Normal fiyat');
        $sale = trim($values['indirimli_fiyat'] ?? '') !== ''
            ? $this->price($values['indirimli_fiyat'], 'İndirimli fiyat') : null;
        if ($sale !== null && $sale >= $regular) {
            throw new \InvalidArgumentException('İndirimli fiyat normal fiyattan düşük olmalı.');
        }
        $stock = trim($values['stok'] ?? '');
        if (!preg_match('/^\d+$/', $stock) || (float) $stock > 100000000) {
            throw new \InvalidArgumentException('Stok 0–100.000.000 arasında tam sayı olmalı.');
        }
        $track = $this->boolean($values['stok_takibi'] ?? '', 'Stok takibi');
        $active = $this->boolean($values['yayinda'] ?? '', 'Yayında');

        $attributes = [
            'name' => $name,
            'primary_category_id' => $category->id,
            'price' => number_format(($sale ?? $regular) / 100, 2, '.', ''),
            'compare_at_price' => $sale !== null ? number_format($regular / 100, 2, '.', '') : null,
            'stock_quantity' => $track ? (int) $stock : max(999, (int) $stock),
            'tracks_stock' => $track,
            'is_active' => $active,
        ];

        $texts = [
            'aciklama' => ['description', 10000],
            'kisa_aciklama' => ['short_description', 10000],
            'seo_baslik' => ['seo_title', 255],
            'seo_aciklama' => ['seo_description', 2000],
            'seo_odak_kelime' => ['seo_focus_keyword', 255],
            'secenek_basligi' => ['option_label', 80],
        ];
        foreach ($texts as $column => [$field, $maximum]) {
            if (!array_key_exists($column, $values)) {
                continue;
            }
            $value = trim($values[$column]);
            if (mb_strlen($value) > $maximum) {
                throw new \InvalidArgumentException($column.' en fazla '.$maximum.' karakter olabilir.');
            }
            $attributes[$field] = $value !== '' ? $value : null;
        }

        if (array_key_exists('agirlik_kg', $values)) {
            $weight = trim($values['agirlik_kg']);
            if ($weight !== '' && (!preg_match('/^\d+(?:[.,]\d{1,3})?$/', $weight) || (float) str_replace(',', '.', $weight) > 9999999)) {
                throw new \InvalidArgumentException('Ağırlık kg cinsinden en fazla 3 ondalık basamakla girilmeli.');
            }
            $attributes['weight_kg'] = $weight === '' ? null : str_replace(',', '.', $weight);
        }

        $options = null;
        if (array_key_exists('secenekler', $values)) {
            $options = $this->tokens($values['secenekler'], 'Seçenek', 30, 60);
            if ($options && !$attributes['option_label']) {
                throw new \InvalidArgumentException('Seçenek kullanıldığında seçenek başlığı zorunludur.');
            }
            if (!$options) {
                $attributes['option_label'] = null;
            }
        }

        $badgeIds = null;
        if (array_key_exists('rozetler', $values)) {
            $badgeIds = [];
            foreach ($this->tokens($values['rozetler'], 'Rozet', 30, 50) as $label) {
                $badge = $badges->get(mb_strtolower($label));
                if (!$badge) {
                    throw new \InvalidArgumentException('Rozet bulunamadı: '.$label);
                }
                $badgeIds[] = $badge->id;
            }
        }

        $categoryIds = null;
        if (array_key_exists('ek_kategori_sluglari', $values)) {
            $categoryIds = [];
            foreach ($this->tokens($values['ek_kategori_sluglari'], 'Ek kategori', 30, 200) as $slug) {
                $extra = $categories->get($slug);
                if (!$extra) {
                    throw new \InvalidArgumentException('Ek kategori bulunamadı: '.$slug);
                }
                if ($extra->id !== $category->id) {
                    $categoryIds[] = $extra->id;
                }
            }
        }

        $cover = null;
        if (array_key_exists('kapak_gorseli', $values) && trim($values['kapak_gorseli']) !== '') {
            $cover = $this->imagePath(trim($values['kapak_gorseli']));
        }
        $gallery = null;
        if (array_key_exists('galeri_gorselleri', $values) && trim($values['galeri_gorselleri']) !== '') {
            $gallery = array_map($this->imagePath(...), $this->tokens($values['galeri_gorselleri'], 'Galeri görseli', 20, 255));
        }
        if ($gallery && $cover && !in_array($cover, $gallery, true) && count($gallery) >= 20) {
            throw new \InvalidArgumentException('Kapak dahil en fazla 20 galeri görseli olabilir.');
        }

        return [
            'sku' => $sku, 'attributes' => $attributes, 'options' => $options,
            'badges' => $badgeIds, 'categories' => $categoryIds,
            'cover' => $cover, 'gallery' => $gallery,
        ];
    }

    private function applyImages(Product $product, ?string $cover, ?array $gallery): void
    {
        if ($cover === null && $gallery === null) {
            return; // An empty CSV image cell never deletes existing photos.
        }

        $paths = array_values(array_unique(array_filter(array_merge(
            $cover ? [$cover] : [], $gallery ?? []
        ))));
        foreach ($paths as $path) {
            if (!Storage::disk('public')->exists(substr($path, 8))) {
                throw new \InvalidArgumentException('Sunucuda bulunmayan görsel: '.$path);
            }
        }
        if ($gallery !== null) {
            // Never delete existing photographs silently during a spreadsheet import.
            $existing = $product->images()->pluck('path')->all();
            $new = array_values(array_diff($paths, $existing));
            if (count($existing) + count($new) > 20) {
                throw new \InvalidArgumentException($product->sku.' için galeri 20 fotoğraf sınırını aşıyor.');
            }
            $order = (int) $product->images()->max('sort_order') + 1;
            foreach ($new as $path) {
                $product->images()->create(['path' => $path, 'sort_order' => $order++]);
            }
        }
        if ($cover !== null) {
            $product->update(['image_path' => $cover]);
        } elseif (!$product->image_path && $paths) {
            $product->update(['image_path' => $paths[0]]);
        }
    }

    private function imagePath(string $path): string
    {
        if (mb_strlen($path) > 255 ||
            !preg_match('~^storage/(?:products|imports/products)/[A-Za-z0-9_./-]+\.(?:png|jpe?g|webp|avif)$~i', $path) ||
            in_array('..', explode('/', $path), true)) {
            throw new \InvalidArgumentException('Görsel yolu yalnızca sunucudaki storage/products veya storage/imports/products altında olmalı.');
        }
        return $path;
    }

    private function tokens(string $value, string $label, int $max, int $maxLength): array
    {
        $tokens = collect(explode('|', $value))->map(fn ($item) => trim($item))
            ->filter(fn ($item) => $item !== '')->values()->all();
        if (count($tokens) > $max || collect($tokens)->contains(fn ($item) => mb_strlen($item) > $maxLength)) {
            throw new \InvalidArgumentException($label.' sayısı veya karakter uzunluğu sınırı aşıldı.');
        }
        if (count(array_unique(array_map('mb_strtolower', $tokens))) !== count($tokens)) {
            throw new \InvalidArgumentException($label.' listesinde tekrar eden değer var.');
        }
        return $tokens;
    }

    private function price(string $raw, string $label): int
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim($raw));
        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        if (!preg_match('/^\d+(?:[.,]\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException($label.' geçersiz. Örnek: 129,90.');
        }
        [$whole, $fraction] = array_pad(preg_split('/[.,]/', $value), 2, '0');
        $cents = ((float) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($cents <= 0 || $cents > 999999999999) {
            throw new \InvalidArgumentException($label.' 0’dan büyük ve izin verilen sınırda olmalı.');
        }
        return (int) $cents;
    }

    private function boolean(string $value, string $label): bool
    {
        return match (mb_strtolower(trim($value))) {
            '1', 'evet', 'true', 'aktif' => true,
            '0', 'hayır', 'hayir', 'false', 'pasif' => false,
            default => throw new \InvalidArgumentException($label.' için 1 veya 0 yazın.'),
        };
    }

    private static function safeForExcel(mixed $value): string
    {
        $text = (string) $value;
        // Never allow CSV formula evaluation when opened in Excel.
        return preg_match('/^[\x00-\x20]*[=+@-]/', $text) ? "'".$text : $text;
    }

    private static function decodeExcelCell(?string $value): string
    {
        $value = (string) $value;
        // Re-import a formula-escaped value that our own exporter wrote.
        return str_starts_with($value, "'") && preg_match('/^[\x00-\x20]*[=+@-]/', substr($value, 1))
            ? substr($value, 1)
            : $value;
    }
}
