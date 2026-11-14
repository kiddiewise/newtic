<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Never populate a new installation with another merchant's catalogue.
 * All storefront content is managed by that installation's administrator.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty. The historical PolarisDemoSeeder is retained
        // only for explicit isolated legacy development/QA fixtures.
    }
}
