<?php

namespace App\Support;

use JsonException;

final class TurkeyDistricts
{
    /**
     * Snapshot of Turkey's province/district data.
     * Source + MIT attribution: docs/TURKEY_ADDRESS_DATA.md.
     *
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        static $districts = null;

        if ($districts === null) {
            $json = file_get_contents(resource_path('data/turkey-districts.json'));
            if ($json === false) {
                throw new \RuntimeException('Türkiye ilçe verisi okunamadı.');
            }
            $districts = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        return $districts;
    }

    /** @return list<string> */
    public static function forProvince(?string $province): array
    {
        return self::all()[$province ?? ''] ?? [];
    }

    public static function belongsToProvince(?string $province, ?string $district): bool
    {
        return is_string($district) && in_array($district, self::forProvince($province), true);
    }
}
