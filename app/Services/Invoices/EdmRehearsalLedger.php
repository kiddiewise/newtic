<?php

namespace App\Services\Invoices;

use App\Models\EdmInvoiceRehearsal;
use App\Models\InvoicePreparation;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Durable, offline-only reservations. No HTTP, invoice numbering, or fiscal status. */
final class EdmRehearsalLedger
{
    public function reserve(int $preparationId, #[\SensitiveParameter] string $xml, string $profile,
        #[\SensitiveParameter] string $senderAlias, #[\SensitiveParameter] string $receiverAlias,
        \DateTimeInterface $actionDate): EdmInvoiceRehearsal
    {
        return DB::transaction(function () use ($preparationId,$xml,$profile,$senderAlias,$receiverAlias,$actionDate) {
            $draft=InvoicePreparation::query()->lockForUpdate()->findOrFail($preparationId);
            $order=Order::query()->findOrFail($draft->order_id);
            if (!$order->is_test || $order->status==='cancelled') throw new DomainException('Yalnızca iptal edilmemiş test siparişi için çevrimdışı prova yapılabilir.');
            $snapshot=$draft->amounts;
            if (!is_array($snapshot) || !hash_equals($draft->snapshot_hash,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
                || ($snapshot['gross_cents']??null)!==$draft->gross_cents) {
                throw new DomainException('Taslak hesap kaydı doğrulanamadı.');
            }
            $request=(new EdmLoadInvoiceRehearsal)->build($xml,$snapshot,$draft->uuid,$profile,$senderAlias,$receiverAlias,$actionDate);
            $digest=hash_hmac('sha256',json_encode([$senderAlias,$receiverAlias],JSON_THROW_ON_ERROR), (string)config('app.key'));
            $existing=EdmInvoiceRehearsal::query()->where('invoice_preparation_id',$draft->id)->first();
            if ($existing) {
                if ($existing->state!=='offline_reserved' || $existing->uuid!==$draft->uuid || $existing->profile!==$profile
                    || !hash_equals($existing->content_sha256,$request['content_sha256']) || !hash_equals($existing->routing_digest,$digest)) {
                    throw new DomainException('Mevcut ETTN için farklı prova kaydı oluşturulamaz.');
                }
                return $existing;
            }
            return EdmInvoiceRehearsal::create([
                'invoice_preparation_id'=>$draft->id,'uuid'=>$draft->uuid,'profile'=>$profile,
                'state'=>'offline_reserved','content_sha256'=>$request['content_sha256'],'routing_digest'=>$digest,
            ]);
        },3);
    }
}
