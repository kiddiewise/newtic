<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UniqueSlug
{
    /** @param class-string<Model> $model */
    public static function make(string $model, string $name): string
    {
        $base = Str::slug($name, '-', 'tr') ?: 'urun';
        $base = mb_substr($base, 0, 190);
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
