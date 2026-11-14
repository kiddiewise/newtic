<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A fresh installation needs its own SEO row before it is exposed on
        // a real domain. Existing merchants/settings are never overwritten.
        if (Schema::hasTable('seo_settings')
            && !DB::table('seo_settings')->where('id', 1)->exists()) {
            DB::table('seo_settings')->insert([
                'id' => 1, 'site_name' => (string) config('app.name', 'Online Mağaza'),
                'title_suffix' => (string) config('app.name', 'Online Mağaza'),
                'noindex' => true, 'nofollow' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Never delete a merchant's customized identity or SEO preferences.
    }
};