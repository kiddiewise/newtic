<?php

namespace App\Support;

use Generator;
use RuntimeException;

final class WooCsvReader
{
    /**
     * WooCommerce's official Products > Export CSV (UTF-8 / RFC 4180).
     * The generator parses one record at a time, including multiline descriptions.
     *
     * @return Generator<int, array{row_number:int,data:array<string,string>}>
     */
    public static function rows(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
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
            if (!is_array($headers)) {
                throw new RuntimeException('CSV sütun başlıkları bulunamadı.');
            }
            $headers = array_map(static fn ($value) => mb_strtolower(trim(
                preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)
            )), $headers);
            if (!in_array('name', $headers, true) || !in_array('type', $headers, true)) {
                throw new RuntimeException('WooCommerce Ürünler → Dışa Aktar dosyası yükleyin (Name ve Type sütunları gerekli).');
            }
            $lineNumber = 1;
            while (($columns = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $lineNumber++;
                if (count($columns) === 1 && trim((string) $columns[0]) === '') {
                    continue;
                }
                if (count($columns) !== count($headers)) {
                    throw new RuntimeException("CSV satırı {$lineNumber}: Sütun sayısı başlıkla uyuşmuyor.");
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
        $type = self::get($row, 'Type');
        return trim(explode(',', $type)[0]);
    }
}
