<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use App\Models\EdmSubmissionAttempt;
use App\Models\InvoicePreparation;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Internal sandbox-only submission journal. A claim is a one-way reservation,
 * NOT a validated payload, authorization to transmit, or evidence of submission.
 * No LoadInvoice transport, routes or scheduler call claim() yet.
 */
final class EdmSubmissionJournal
{
    private function outsideTransaction(): void
    {
        if (DB::transactionLevel()!==0) throw new DomainException('EDM işlem kaydı bağımsız veritabanı işlemi gerektirir.');
    }

    private function account(): EdmSetting
    {
        $account=EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
        if (!$account?->configured() || !$account->revision || $account->probe_status!=='ok') {
            throw new DomainException('EDM test hesabı doğrulanmamış.');
        }
        return $account;
    }

    /**
     * Future caller MUST first validate the actual provider-ready payload,
     * account routing and explicit operator authorization. This method only
     * commits the irreversible reservation before any network mutation.
     */
    public function claim(int $preparationId,string $profile,string $payloadSha256,string $expectedAccountRevision): EdmSubmissionAttempt
    {
        $this->outsideTransaction();
        if (!in_array($profile,['TEMELFATURA','TICARIFATURA'],true)
            || !preg_match('/^[0-9a-f]{64}$/D',$payloadSha256)) {
            throw new DomainException('Geçersiz EDM gönderim kaydı.');
        }

        return DB::transaction(function () use ($preparationId,$profile,$payloadSha256,$expectedAccountRevision) {
            $account=$this->account();
            if (!hash_equals($account->revision,$expectedAccountRevision)) throw new DomainException('EDM hesabı değişmiş.');
            $candidate=InvoicePreparation::query()->findOrFail($preparationId);
            // Same lock ordering as InvoicePreparationService: order first.
            $order=Order::query()->lockForUpdate()->findOrFail($candidate->order_id);
            $draft=InvoicePreparation::query()->lockForUpdate()->findOrFail($preparationId);
            if ($draft->order_id!==$order->id || !$order->is_test
                || !in_array($order->status,['preparing','shipped','out_for_delivery','completed'],true)
                || $order->currency!=='TRY' || $order->total_cents!==$draft->gross_cents) {
                throw new DomainException('Gönderim kaydı yalnızca uygun test siparişine açılabilir.');
            }
            $snapshot=$draft->amounts;
            if (!is_array($snapshot) || !is_string($draft->snapshot_hash)
                || !hash_equals($draft->snapshot_hash,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
                || ($snapshot['gross_cents']??null)!==$draft->gross_cents) {
                throw new DomainException('Fatura hesap kaydı tutarsız.');
            }
            InvoiceSnapshotRules::validate($snapshot);
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$draft->uuid)) {
                throw new DomainException('Geçersiz ETTN.');
            }
            if (EdmSubmissionAttempt::query()->where('invoice_preparation_id',$draft->id)->orWhere('uuid',$draft->uuid)->exists()) {
                throw new DomainException('Bu ETTN için işlem hakkı daha önce ayrılmış. Yeniden gönderilemez; durum sorgulanmalı.');
            }
            $attempt=new EdmSubmissionAttempt();
            $attempt->forceFill([
                'invoice_preparation_id'=>$draft->id,'uuid'=>$draft->uuid,
                'account_revision'=>$account->revision,'profile'=>$profile,'payload_sha256'=>$payloadSha256,
                // Even a crash immediately after commit must not unlock resend.
                'state'=>'outcome_unknown',
            ])->save();
            return $attempt;
        },3);
    }

    /**
     * Read-only reconciliation. No claim, resend, or change to order state.
     * The account revision and query generation are checked after the HTTP call.
     */
    public function reconcile(int $attemptId, EdmTestClient $client): EdmSubmissionAttempt
    {
        $this->outsideTransaction();
        [$attempt,$account,$generation]=DB::transaction(function () use ($attemptId) {
            $account=$this->account();
            $attempt=EdmSubmissionAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if (!hash_equals($attempt->account_revision,$account->revision)) throw new DomainException('Bu ETTN farklı EDM hesap revizyonuna ait.');
            $generation=$attempt->lookup_generation+1;
            $attempt->forceFill(['lookup_generation'=>$generation])->save();
            return [$attempt,$account,$generation];
        },3);

        try {
            $result=$client->invoiceStatus($account->username,$account->password,$attempt->uuid,$attempt->profile);
        } catch (Throwable) {
            $result=['status'=>'failed','data'=>null];
        }

        return DB::transaction(function () use ($attemptId,$account,$generation,$result) {
            $current=EdmSetting::query()->whereKey(1)->lockForUpdate()->first();
            $row=EdmSubmissionAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            if (!$current || $current->revision!==$account->revision
                || $row->account_revision!==$account->revision || $row->lookup_generation!==$generation) {
                throw new DomainException('Eski veya farklı hesaba ait EDM sorgu sonucu kaydedilmedi.');
            }
            $data=$result['data']??null;
            $found=($result['status']??null)==='ok' && is_array($data)
                && ($data['uuid']??null)===strtolower($row->uuid);
            $values=['last_lookup_state'=>$found?'matched':'unconfirmed','last_lookup_at'=>now()];
            if ($found) {
                $number=$data['invoice_number']??null;
                if ($row->observed_invoice_number!==null && $number!==null && $number!==$row->observed_invoice_number) {
                    $values['last_lookup_state']='identity_conflict';
                } else {
                    $code=$data['gib']['code']??null;
                    $values+=[
                        'state'=>'provider_record_found',
                        'last_portal_state'=>$this->allowed($data['portal_state']??null,['send_succeeded','accept_succeeded','unknown']),
                        'last_gib_code'=>is_string($code)&&preg_match('/^[0-9]{4}$/D',$code)?$code:null,
                        'last_commercial_response'=>$this->allowed($data['commercial_response']??null,['accepted','rejected','not_reported','unknown','not_applicable']),
                        'last_archive_report'=>$this->allowed($data['archive_report']??null,['not_reported','uninterpreted','not_applicable']),
                        'last_archive_cancel_report'=>$this->allowed($data['archive_cancel_report']??null,['not_reported','uninterpreted','not_applicable']),
                        'observed_invoice_number'=>is_string($number)&&preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$number)
                            && $number!==UblSaleCandidate::EDM_SERIAL_SENTINEL ? $number : $row->observed_invoice_number,
                    ];
                }
            }
            // A failed lookup never deletes earlier evidence or unlocks send.
            $row->forceFill($values)->save();
            return $row;
        },3);
    }

    private function allowed(mixed $value,array $allowed): string
    {
        return is_string($value)&&in_array($value,$allowed,true)?$value:'unknown';
    }
}
