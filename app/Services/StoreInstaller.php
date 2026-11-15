<?php

namespace App\Services;

use App\Models\SeoSetting;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class StoreInstaller
{
    public const VERSION = '1.0.0';

    private function directory(): string
    {
        return (string) config('newtic.install_private_directory', storage_path('app/private/newtic-setup'));
    }

    private function path(string $name): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$name;
    }

    public function hasPending(): bool
    {
        return File::isFile($this->path('pending.json'));
    }

    public function hasLock(): bool
    {
        return File::exists($this->path('installed.lock'));
    }

    public function installed(): bool
    {
        if ($this->hasLock()) return true;
        if (!Schema::hasTable('newtic_installations')) return false;

        return DB::table('newtic_installations')->exists();
    }

    public function pristine(): bool
    {
        if ($this->installed()) return false;
        foreach (['users', 'orders', 'products', 'categories'] as $name) {
            if (!Schema::hasTable($name) || DB::table($name)->exists()) return false;
        }

        return true;
    }

    /** @return array<string, bool> */
    public function checks(?Request $request = null): array
    {
        $test = app()->environment('testing');
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $domainIsSafe = $test || (
            filter_var('https://'.$host, FILTER_VALIDATE_URL)
            && !str_ends_with(strtolower($host), '.hostingersite.com')
            && str_starts_with((string) config('app.url'), 'https://')
        );

        $dbOk = false;
        $fresh = false;
        try {
            DB::connection()->getPdo();
            $dbOk = Schema::hasTable('newtic_installations');
            if ($dbOk) $fresh = $this->pristine();
        } catch (\Throwable) {
            // Do not expose database connection errors to unauthenticated visitors.
        }

        return [
            'PHP 8.3 veya üzeri' => PHP_VERSION_ID >= 80300,
            'PHP uzantıları' => collect(['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'xml', 'ctype', 'tokenizer'])
                ->every(fn ($extension) => $test || extension_loaded($extension)),
            'Uygulama şifreleme anahtarı' => filled(config('app.key')),
            'Üretim ayarları' => $test || (app()->environment('production') && !config('app.debug')),
            'Güvenli ve bağımsız domain' => (bool) $domainIsSafe
                && ($test || !$request || ($request->isSecure() && strcasecmp($request->getHost(), $host) === 0)), 
            'MySQL bağlantısı ve tablolar' => $dbOk && ($test || config('database.default') === 'mysql'),
            'Yeni, boş mağaza veritabanı' => $fresh,
            'Dosya yazma izinleri' => is_writable(storage_path('app'))
                && is_writable(storage_path('framework'))
                && is_writable(base_path('bootstrap/cache')),
            'Test ödeme modu etkin' => (bool) config('newtic.checkout_test_mode', true),
            'Gerçek ödeme ve kargo kapalı' => !config('newtic.paytr_card_payments_enabled', false)
                && !config('newtic.paytr_webhooks_enabled', false)
                && !config('newtic.shipping_dummy_enabled', false),
        ];
    }

    public function ready(?Request $request = null): bool
    {
        foreach ($this->checks($request) as $passed) {
            if (!$passed) return false;
        }

        return true;
    }

    public function issueToken(): string
    {
        if (!$this->pristine()) {
            throw new \RuntimeException('Setup requires an empty NEWTIC database without existing accounts.');
        }
        if (!$this->ready()) {
            throw new \RuntimeException('NEWTIC server prerequisites failed. Review installation preflight configuration.');
        }
        if ($this->hasLock()) {
            throw new \RuntimeException('Store already installed.');
        }

        $token = bin2hex(random_bytes(32));
        File::ensureDirectoryExists($this->directory(), 0700, true);
        $old = umask(0077);
        try {
            $written = File::put($this->path('pending.json'), json_encode([
                'digest' => hash('sha256', $token),
                'expires' => now()->addHours(2)->getTimestamp(),
            ], JSON_THROW_ON_ERROR));
            if ($written === false) throw new \RuntimeException('Unable to write private setup token.');
            @chmod($this->path('pending.json'), 0600);
        } finally {
            umask($old);
        }

        return $token;
    }

    private function tokenRecord(): ?array
    {
        if (!$this->hasPending()) return null;
        try {
            $raw = json_decode(File::get($this->path('pending.json')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($raw) || !is_string($raw['digest'] ?? null)
            || !is_numeric($raw['expires'] ?? null)
            || now()->getTimestamp() > (int) $raw['expires']) return null;

        return $raw;
    }

    public function validToken(string $token): bool
    {
        $record = $this->tokenRecord();

        return $record !== null && preg_match('/^[0-9a-f]{64}$/D', $token)
            && hash_equals($record['digest'], hash('sha256', $token));
    }

    public function tokenFingerprint(): ?string
    {
        return $this->tokenRecord()['digest'] ?? null;
    }

    public function sessionAuthorized(Request $request): bool
    {
        $hash = $this->tokenFingerprint();

        return $hash !== null
            && is_string($request->session()->get('newtic_setup_digest'))
            && hash_equals($hash, $request->session()->get('newtic_setup_digest'));
    }

    public function complete(array $data, Request $request): User
    {
        if (!$this->hasPending() || !$this->sessionAuthorized($request)
            || !$this->ready($request)) {
            throw ValidationException::withMessages([
                'installation' => 'Kurulum oturumu veya sunucu kontrolü geçersiz. Güvenli anahtarla yeniden başlayın.',
            ]);
        }

        // Unique id=1 protects against concurrent tab submissions. The admin,
        // store identity and installation lock record commit atomically.
        $user = DB::transaction(function () use ($data) {
            if (!$this->pristine()) {
                throw ValidationException::withMessages(['installation' => 'Mağaza zaten kurulmuş veya veritabanı boş değil.']);
            }
            $domain = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            $admin = new User();
            $admin->forceFill([
                'name' => trim($data['admin_name']),
                'email' => mb_strtolower(trim($data['admin_email'])),
                'password' => $data['password'],
                'is_admin' => true,
                'email_verified_at' => null,
            ])->save();

            SeoSetting::query()->updateOrCreate(['id' => 1], [
                'site_name' => trim($data['store_name']),
                'title_suffix' => trim($data['store_name']),
                'default_description' => trim($data['store_name']).' online mağazası.',
                'noindex' => true,
                'nofollow' => false,
            ]);
            StoreSetting::query()->firstOrCreate(['id' => 1], [
                'free_shipping_threshold' => '0.00', 'shipping_fee' => '0.00',
            ]);

            DB::table('newtic_installations')->insert([
                'id' => 1, 'version' => self::VERSION, 'domain' => $domain,
                'admin_user_id' => $admin->id,
                'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $admin;
        }, 3);

        // The transactional singleton row and first-admin check also secure
        // installations when disk writes are temporarily unavailable.
        try {
            $old = umask(0077);
            File::put($this->path('installed.lock'), 'NEWTIC installed '.now()->toIso8601String());
            @chmod($this->path('installed.lock'), 0600);
            File::delete($this->path('pending.json'));
        } finally {
            umask($old ?? 0077);
        }

        return $user;
    }
}
