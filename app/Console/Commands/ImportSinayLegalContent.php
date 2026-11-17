<?php

namespace App\Console\Commands;

use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Support\SinayLegalContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ImportSinayLegalContent extends Command
{
    protected $signature = 'newtic:sinay-legal {--publish : Import reviewed Sinay-specific documents without overriding existing admin text}';
    protected $description = 'Install Sinay Design legal documentation on the verified, isolated Sinay shop only.';

    public function handle(): int
    {
        $domain = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $isTest = app()->environment('testing');
        $shopPath = '/domains/sinaydesign.com/newtic';

        if ($domain !== SinayLegalContent::DOMAIN
            || (!$isTest && !str_contains(base_path(), $shopPath))
            || (!$isTest && app()->environment() !== 'production')
            || (!$isTest && config('database.default') !== 'mysql')
            || (!$isTest && !config('newtic.checkout_test_mode', false))) {
            $this->error('Refusing: Sinay importer is permitted only for its verified isolated test-payment shop.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('merchant_legal_pages') || !Schema::hasTable('merchant_legal_profiles')) {
            $this->error('Legal data tables do not exist.');
            return self::FAILURE;
        }

        $documents = SinayLegalContent::pages();

        if (!$this->option('publish')) {
            $this->line('Preview: '.count($documents).' Sinay legal documents, no data changed.');
            return self::SUCCESS;
        }

        $created = [];
        $preserved = [];
        DB::transaction(function () use ($documents, &$created, &$preserved) {
            $profile = MerchantLegalProfile::query()->find(1) ?? new MerchantLegalProfile();
            $profile->id = 1;
            foreach (SinayLegalContent::profile() as $key => $value) {
                if ($value !== null && $key !== 'show_paytr_disclosure' && blank($profile->{$key})) {
                    $profile->{$key} = $value;
                }
            }
            if (!$profile->exists) $profile->show_paytr_disclosure = false;
            $profile->save();

            foreach ($documents as $slug => $document) {
                if (!array_key_exists($slug, MerchantLegalPage::LABELS)
                    || mb_strlen($document['body']) < 200) {
                    throw new \RuntimeException('Incomplete or unsupported Sinay legal text.');
                }
                if (MerchantLegalPage::query()->where('slug', $slug)->lockForUpdate()->exists()) {
                    $preserved[] = $slug;
                    continue;
                }
                MerchantLegalPage::query()->create([
                    'slug' => $slug,
                    'title' => $document['title'],
                    'body' => $document['body'],
                    'version' => 1,
                    'is_published' => true,
                    'published_at' => now(),
                ]);
                $created[] = $slug;
            }
        }, 3);

        $this->info('Sinay legal import completed: '.count($created).' published, '.count($preserved).' previously edited pages preserved.');
        foreach ($created as $slug) $this->line('PUBLISHED: /kurumsal/'.$slug);
        foreach ($preserved as $slug) $this->warn('UNCHANGED EXISTING: /kurumsal/'.$slug);

        $missing = [];
        $profile = MerchantLegalProfile::current();
        foreach (['registered_address', 'contact_email', 'contact_phone'] as $key) {
            if (blank($profile->{$key})) $missing[] = $key;
        }
        if ($missing) {
            $this->warn('MANDATORY SELLER DETAILS MISSING: '.implode(', ', $missing).'. Keep live checkout disabled.');
        }
        $this->warn('Documents require review by a qualified Turkish lawyer and verification of actual business practices.');
        $this->line('No tax IDs, legal address, trust seals, payment settings or customer data were fabricated or changed.');
        return self::SUCCESS;
    }
}
