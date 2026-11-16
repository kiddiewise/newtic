<?php

namespace App\Console\Commands;

use App\Services\AdminRecoveryService;
use Illuminate\Console\Command;

final class PrepareAdminRecovery extends Command
{
    protected $signature = 'newtic:admin-recovery {--private-file : Create a secure one-time recovery code for the hosting account file manager}';
    protected $description = 'Safely prepare temporary admin password recovery without emailing or printing secrets.';

    public function handle(AdminRecoveryService $recovery): int
    {
        if (!$this->option('private-file')) {
            $this->error('Kurtarma kodu yalnızca --private-file seçeneğiyle özel dosyaya yazılabilir.');

            return self::FAILURE;
        }

        try {
            $recovery->preparePrivateRecovery();
        } catch (\Throwable $e) {
            $this->error('Kurtarma hazırlığı başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Yönetici kurtarma kodu özel hosting dosyasına kaydedildi.');
        $this->line('Dosya: storage/app/private/newtic-admin-recovery/private-admin-recovery.txt');
        $this->line('Kod 30 dakika geçerlidir; GitHub Actions veya SSH çıktılarına yazdırılmadı.');
        $this->line('Şifre ancak yetkili kişi kodu tarayıcıda kullanınca değiştirilecektir.');

        return self::SUCCESS;
    }
}
