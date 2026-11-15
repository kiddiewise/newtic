<?php

namespace App\Console\Commands;

use App\Services\StoreInstaller;
use Illuminate\Console\Command;

final class PrepareSetupWizard extends Command
{
    protected $signature = 'newtic:setup-token';
    protected $description = 'Print one short-lived first-install unlock code for a pristine, isolated NEWTIC store.';

    public function handle(StoreInstaller $installer): int
    {
        try {
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
