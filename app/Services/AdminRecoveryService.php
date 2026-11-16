<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminRecoveryService
{
    public const EXPIRY_MINUTES = 30;

    private function directory(): string
    {
        return (string) config('newtic.admin_recovery_private_directory', storage_path('app/private/newtic-admin-recovery'));
    }

    private function path(string $filename): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$filename;
    }

    private function record(): ?array
    {
        if (!File::isFile($this->path('access.json'))) {
            return null;
        }
        try {
            $record = json_decode(File::get($this->path('access.json')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($record) || !is_int($record['user_id'] ?? null)
            || !is_string($record['hash'] ?? null)
            || !preg_match('/^[0-9a-f]{64}$/D', $record['hash'])
            || !is_numeric($record['expires_at'] ?? null)
            || now()->getTimestamp() >= (int) $record['expires_at']) {
            return null;
        }

        return $record;
    }

    public function active(): bool
    {
        return $this->record() !== null;
    }

    /** 
     * Issue a time-limited, one-time code for the sole existing administrator.
     * Nothing secret is returned or printed to GitHub Actions or SSH stdout.
     */
    public function preparePrivateRecovery(): void
    {
        $admins = User::query()->where('is_admin', true)->limit(2)->get();
        if ($admins->count() !== 1) {
            throw new \RuntimeException('Recovery requires exactly one existing admin account; no accounts were changed.');
        }

        $admin = $admins->first();
        $token = Password::broker('users')->createToken($admin);
        $record = json_encode([
            'user_id' => $admin->id,
            'hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        File::ensureDirectoryExists($this->directory(), 0700, true);
        $previousUmask = umask(0077);
        try {
            if (File::put($this->path('access.json'), $record) === false) {
                throw new \RuntimeException('Private recovery state could not be saved.');
            }
            @chmod($this->path('access.json'), 0600);
            $privateText = "NEWTIC yönetici hesap kurtarma\n"
                ."Yönetici e-posta adresi: ".$admin->email."\n"
                ."Kurtarma ekranı: ".rtrim(config('app.url'), '/')."/admin/kurtarma\n"
                ."Tek kullanımlık kod: ".$token."\n"
                ."Bu kod ".self::EXPIRY_MINUTES." dakika geçerlidir. Kodu kimseyle paylaşmayın.\n";
            if (File::put($this->path('private-admin-recovery.txt'), $privateText) === false) {
                File::delete($this->path('access.json'));
                throw new \RuntimeException('Private recovery instructions could not be saved.');
            }
            @chmod($this->path('private-admin-recovery.txt'), 0600);
        } finally {
            umask($previousUmask);
        }
    }

    public function reset(string $token, string $newPassword): void
    {
        $record = $this->record();
        if ($record === null || !hash_equals($record['hash'], hash('sha256', $token))) {
            throw ValidationException::withMessages([
                'recovery_code' => 'Kurtarma kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        $admin = User::query()->whereKey($record['user_id'])->where('is_admin', true)->first();
        if (!$admin) {
            throw ValidationException::withMessages([
                'recovery_code' => 'Yönetici hesabı bulunamadı.',
            ]);
        }

        $result = Password::broker('users')->reset([
            'email' => $admin->email,
            'token' => $token,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ], function (User $user, string $password) use ($admin): void {
            if (!$user->is_admin || $user->id !== $admin->id) {
                throw new \RuntimeException('Account recovery identity mismatch.');
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            // Terminate old database-backed browser sessions, if enabled.
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'recovery_code' => 'Kurtarma kodu geçersiz veya süresi dolmuş.',
            ]);
        }

        File::delete([
            $this->path('access.json'),
            $this->path('private-admin-recovery.txt'),
        ]);
    }
}
