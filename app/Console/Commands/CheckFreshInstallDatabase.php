<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A read-only safety gate before any first-install database migration.
 * A fresh store must have a dedicated, completely empty MySQL schema.
 * No queries in this command can alter customer data.
 */
final class CheckFreshInstallDatabase extends Command
{
    protected $signature = 'newtic:check-fresh-db';
    protected $description = 'Refuse installation into any non-empty or inaccessible MySQL database.';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Fresh customer installations require an isolated MySQL connection.');
            return self::FAILURE;
        }

        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'mysql') {
                $this->error('Configured database driver is not MySQL.');
                return self::FAILURE;
            }

            $name = (string) $connection->getDatabaseName();
            $selected = $connection->selectOne('SELECT DATABASE() AS selected_schema');
            if ($name === '' || $name !== (string) ($selected->selected_schema ?? '')) {
                $this->error('MySQL schema selection does not match the configured database.');
                return self::FAILURE;
            }

            // SHOW FULL TABLES includes views and is read-only.
            if (count($connection->select('SHOW FULL TABLES')) !== 0) {
                $this->error('Database is not empty. Existing records and tables were left untouched.');
                return self::FAILURE;
            }
        } catch (Throwable) {
            // Never echo database driver exception bodies or secret connection data.
            $this->error('Cannot verify MySQL connection or schema. Check credentials, database name and account grants.');
            return self::FAILURE;
        }

        $this->info('NEWTIC_FRESH_DATABASE_OK: configured MySQL schema is empty.');
        return self::SUCCESS;
    }
}
