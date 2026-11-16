<?php

namespace App\Console\Commands;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Support\PolarisLegalContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ImportPolarisLegalContent extends Command
{
    protected $signature = 'newtic:polaris-legal {--publish : Explicitly insert and publish source-based texts}';
    protected $description = 'Publish Polaris legal and footer content ONLY on the isolated Polaris staging website.';

    public function handle(): int
    {
        $domain = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $isTest = app()->environment('testing');
        $stagingDomain = 'powderblue-dinosaur-538060.hostingersite.com';

        if ($domain !== $stagingDomain
            || (!$isTest && !str_contains(base_path(), '/domains/'.$stagingDomain.'/newtic'))
            || (!$isTest && app()->environment() !== 'production')
            || !config('newtic.checkout_test_mode', true)
            || (!$isTest && config('database.default') !== 'mysql')) {
            $this->error('Refusing: this importer runs only on the protected Polaris test store.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('merchant_legal_pages') || !Schema::hasTable('merchant_legal_profiles')) {
            $this->error('Legal-page migrations have not run.');
            return self::FAILURE;
        }

        $pages = PolarisLegalContent::pages();
        if (!$this->option('publish')) {
            $this->line('Preview only: '.count($pages).' source-based Polaris documents available.');
            $this->line('Target: '.$stagingDomain.'; no database data changed.');
            $this->line('Use --publish after verifying source attribution and merchant identity.');
            return self::SUCCESS;
        }

        $created = [];
        $preserved = [];
        DB::transaction(function () use ($pages, &$created, &$preserved): void {
            $profile = MerchantLegalProfile::query()->firstOrNew(['id' => 1]);
            foreach (PolarisLegalContent::profile() as $key => $value) {
                // Never overwrite business details that an administrator
                // already entered; unknown fields remain blank.
                if ($value !== null && $key !== 'show_paytr_disclosure'
                    && blank($profile->{$key})) {
                    $profile->{$key} = $value;
                }
            }
            if (!$profile->exists) {
                $profile->show_paytr_disclosure = false;
            }
            $profile->save();

            foreach ($pages as $slug => $data) {
                if (!array_key_exists($slug, MerchantLegalPage::LABELS)
                    || mb_strlen($data['body']) < 200) {
                    throw new \RuntimeException('Unsupported or incomplete Polaris legal document.');
                }

                $existing = MerchantLegalPage::query()->where('slug', $slug)->lockForUpdate()->first();
                if ($existing) {
                    $preserved[] = $slug;
                    continue;
                }

                MerchantLegalPage::query()->create([
                    'slug' => $slug,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'version' => 1,
                    'is_published' => true,
                    'published_at' => now(),
                ]);
                $created[] = $slug;
            }
        }, 3);

        $this->info('Polaris-only import completed: '.count($created).' published, '.count($preserved).' previously existing pages preserved.');
        foreach ($created as $slug) {
            $this->line('PUBLISHED: /kurumsal/'.$slug);
        }
        foreach ($preserved as $slug) {
            $this->warn('UNCHANGED EXISTING: /kurumsal/'.$slug.' (review manually in admin)');
        }
        $this->line('Private merchant financial IDs, ETBIS status, trust logos and payment settings were NOT fabricated or changed.');
        $this->line('Existing WooCommerce polariskursun.com was never accessed for write operations.');

        return self::SUCCESS;
    }
}
