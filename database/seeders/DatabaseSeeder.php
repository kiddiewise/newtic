<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo storefront data is opt-in on staging; run php artisan db:seed --class=PolarisDemoSeeder --force.
        if (app()->environment('local', 'testing')) {
            $this->call(PolarisDemoSeeder::class);
        }
    }
}
