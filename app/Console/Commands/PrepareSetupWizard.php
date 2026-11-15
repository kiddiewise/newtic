<?php

namespace App\Console\Commands;

use App\Services\StoreInstaller;
use Illuminate\Console\Command;

final class PrepareSetupWizard extends Command
{
    protected $signature = 'newtic:setup-token {--private-file : Save the short-lived code to a hosting-account-only file instead of printing it}';
    protected $description = 'Print one short-lived first-install unlock code for a pristine, isolated NEWTIC store.';

    public function handle(StoreInstaller $installer): int
    {
        try {
            if ($this->option('private-file')) {
                $installer->issuePrivateFileToken();
                $this->info('Private installation code file created for the hosting file manager.');
                $this->line('Path: newtic/storage/app/private/newtic-setup/mobile-install-code.txt');
                $this->line('Code expires after 2 hours and the file is removed after setup completes.');
                return self::SUCCESS;
            }
            $token = $installer->issueToken();
        } catch (\Throwable $e) {
            $this->error('Kurulum anahtarı oluşturulamadı: '.$e->getMessage());

            return self::FAILURE;
        }

        $domain = rtrim((string) config('app.url'), '/');
        $this->info('NEWTIC kurulumu için iki saatlik tek mağazaya özel anahtar hazır.');
        $this->line('Kurulum adresi: '.$domain.'/install');
        $this->newLine();
        $this->warn('Bu anahtarı yalnızca güvenilir özel SSH terminalinde görüntüleyin; paylaşmayın veya kaydetmeyin.');
        $this->line('Kurulum anahtarı: '.$token);
        $this->newLine();
        $this->line('Kurulum bitene kadar mağaza ziyaretçilerine açılmaz.');
        $this->line('Süresi dolarsa bu komutu yeniden çalıştırarak önceki anahtarı geçersiz kılabilirsiniz.');

        return self::SUCCESS;
    }
}
