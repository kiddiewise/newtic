<?php

namespace App\Support;

use Generator;
use RuntimeException;

final class WooCsvReader
{
    /**
     * WooCommerce localizes its standard export headings. Keep one canonical
     * internal vocabulary so a Turkish wp-admin export behaves exactly like
     * the English export used by WooCommerce's documentation.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'kimlik' => 'id',
        'tür' => 'type',
        'stok kodu (sku)' => 'sku',
        'gtin, upc, ean veya isbn' => 'gtin, upc, ean, or isbn',
        'isim' => 'name',
        'yayımlanmış' => 'published',
        'öne çıkan?' => 'is featured?',
        'katalogda görünürlük' => 'visibility in catalog',
        'kısa açıklama' => 'short description',
        'açıklama' => 'description',
        'indirimli fiyatın başladığı tarih' => 'date sale price starts',
        'indirimli fiyatın bittiği tarih' => 'date sale price ends',
        'vergi durumu' => 'tax status',
        'vergi sınıfı' => 'tax class',
        'stokta?' => 'in stock?',
        'stok' => 'stock',
        'düşük stok miktarı' => 'low stock amount',
        'ön siparişe izin ver?' => 'backorders allowed?',
        'ayrı ayrı mı satılıyor?' => 'sold individually?',
        'ağırlık (kg)' => 'weight (kg)',
        'ağırlık (g)' => 'weight (g)',
        'uzunluk (cm)' => 'length (cm)',
        'genişlik (cm)' => 'width (cm)',
        'yükseklik (cm)' => 'height (cm)',
        'müşteri değerlendirmelerine izin verilsin mi?' => 'allow customer reviews?',
        'satın alma notu' => 'purchase note',
        'indirimli satış fiyatı' => 'sale price',
        'normal fiyat' => 'regular price',
        'kategoriler' => 'categories',
        'etiketler' => 'tags',
        'gönderim sınıfı' => 'shipping class',
        'görseller' => 'images',
        'indirme sınırı' => 'download limit',
        'indirme sona erme günü' => 'download expiry days',
        'ebeveyn' => 'parent',
        'gruplanmış ürünler' => 'grouped products',
        'yukarı satışlar' => 'upsells',
        'çapraz satışlar' => 'cross-sells',
        'harici url' => 'external url',
        'düğme metni' => 'button text',
        'konum' => 'position',
        'markalar' => 'brands',
    ];

    /**
     * WooCommerce's official Products > Export CSV (UTF-8 / RFC 4180).
     * The generator parses one record at a time, including multiline descriptions.
     *
     * @return Generator<int, array{row_number:int,data:array<string,string>}>
     */
    public static function rows(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('Dışa aktarma dosyası okunamadı.');
        }

        try {
            $line = fgets($handle);
            if ($line === false) {
                throw new RuntimeException('CSV dosyası boş.');
            }
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            if (! is_array($headers)) {
                throw new RuntimeException('CSV sütun başlıkları bulunamadı.');
            }
            $headers = array_map(static fn ($value) => self::canonicalHeader((string) $value), $headers);
            if (! in_array('name', $headers, true) || ! in_array('type', $headers, true)) {
                throw new RuntimeException('WooCommerce Ürünler → Dışa Aktar dosyası yükleyin (İsim/Name ve Tür/Type sütunları gerekli).');
            }
            $namedHeaders = array_filter($headers, static fn ($header) => $header !== '');
            if (count($namedHeaders) !== count(array_unique($namedHeaders))) {
                throw new RuntimeException('CSV dosyasında aynı anlama gelen yinelenen sütun başlıkları var.');
            }
            $lineNumber = 1;
            while (($columns = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $lineNumber++;
                if (count($columns) === 1 && trim((string) $columns[0]) === '') {
                    continue;
                }
                // WooCommerce may omit only trailing empty custom-meta cells.
                // PowerShell/Excel already treats those as blanks; mirror that
                // tolerant RFC 4180 behavior without accepting extra columns.
                if (count($columns) > count($headers)) {
                    throw new RuntimeException("CSV satırı {$lineNumber}: Sütun sayısı başlıkla uyuşmuyor.");
                }
                if (count($columns) < count($headers)) {
                    $columns = array_pad($columns, count($headers), '');
                }
                $data = [];
                foreach ($headers as $i => $header) {
                    if ($header !== '') {
                        $data[$header] = trim((string) $columns[$i]);
                    }
                }
                yield ['row_number' => $lineNumber, 'data' => $data];
            }
        } finally {
            fclose($handle);
        }
    }

    public static function get(array $data, string ...$names): string
    {
        foreach ($names as $name) {
            $key = mb_strtolower($name);
            if (array_key_exists($key, $data)) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    public static function type(array $row): string
    {
        $type = mb_strtolower(trim(explode(',', self::get($row, 'Type'))[0]));

        return match ($type) {
            'basit' => 'simple',
            'değişken' => 'variable',
            'varyasyon' => 'variation',
            'gruplandırılmış' => 'grouped',
            'harici' => 'external',
            default => $type,
        };
    }

    private static function canonicalHeader(string $value): string
    {
        $value = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value));
        // Unicode lowercasing turns Turkish capital İ into "i" plus a
        // combining dot. Normalize it first so exact aliases stay portable
        // even when the PHP intl extension is unavailable.
        $header = mb_strtolower(str_replace('İ', 'i', $value));
        if (preg_match('/^(?:nitelik|özellik|öznitelik)\s+(\d+)\s+(?:adı|ismi)$/u', $header, $match)) {
            return 'attribute '.$match[1].' name';
        }
        if (preg_match('/^(?:nitelik|özellik|öznitelik)\s+(\d+)\s+değer(?:\(ler\)i|leri)?$/u', $header, $match)) {
            return 'attribute '.$match[1].' value(s)';
        }
        if (preg_match('/^(?:nitelik|özellik|öznitelik)\s+(\d+)\s+görünür$/u', $header, $match)) {
            return 'attribute '.$match[1].' visible';
        }
        if (preg_match('/^(?:nitelik|özellik|öznitelik)\s+(\d+)\s+(?:genel|global)$/u', $header, $match)) {
            return 'attribute '.$match[1].' global';
        }

        return self::HEADER_ALIASES[$header] ?? $header;
    }
}
