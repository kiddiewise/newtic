<?php

namespace App\Console\Commands;

use App\Services\AdminWebPush;
use Illuminate\Console\Command;

final class SendAdminOrderPush extends Command
{
    protected $signature = 'admin:push-orders';
    protected $description = 'Deliver bounded, privacy-safe Web Push alerts for new unread orders';

    public function handle(AdminWebPush $push): int
    {
        $this->info('Push accepted: '.$push->deliverPending());
        return self::SUCCESS;
    }
}
