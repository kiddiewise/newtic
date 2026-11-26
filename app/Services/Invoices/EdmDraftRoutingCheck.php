<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use DomainException;

/**
 * Read-only sandbox routing gate. Never calls GetInvoiceSerial (which may
 * allocate a number), LoadInvoice or SendInvoice. No automatic archive
 * decision from empty CheckUser records.
 */
final class EdmDraftRoutingCheck
{
    public function __construct(private EdmTestClient $client) {}

    public function verify(EdmSetting $account, #[\SensitiveParameter] string $seller,
        #[\SensitiveParameter] string $buyer, #[\SensitiveParameter] string $senderAlias,
        #[\SensitiveParameter] string $receiverAlias, string $profile, string $issueDate,
        string $invoiceId): void
    {
        if (!$account->configured() || !$account->revision || $account->probe_status!=='ok'
            || !in_array($profile,['TEMELFATURA','TICARIFATURA'],true)
            || !preg_match('/^(20[0-9]{2})-([0-9]{2})-([0-9]{2})$/D',$issueDate,$m)
            || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])
            || !preg_match('/^[A-Z0-9]{3}[0-9]{13}$/D',$invoiceId)) {
            throw new DomainException('EDM yönlendirme ön koşulları sağlanmadı.');
        }
        foreach ([$seller,$buyer] as $identifier) {
            if (!preg_match('/^[0-9]{10,11}$/D',$identifier) || $identifier==='11111111111') {
                throw new DomainException('e-Fatura taraf kimliği geçersiz.');
            }
        }
        foreach ([$senderAlias,$receiverAlias] as $alias) {
            if (!preg_match('/^urn:mail:[^\s<>]{1,250}$/Di',$alias)) throw new DomainException('EDM etiketi geçersiz.');
        }
        $this->unchanged($account);
        $company=$this->client->company($account->username,$account->password,$seller);
        $data=$company['data']??null;
        if (($company['status']??null)!=='ok' || !is_array($data)
            || ($data['gb']??null)!==$senderAlias || ($data['unreadable_series']??0)!==0) {
            throw new DomainException('EDM gönderici firma veya seri bilgisi doğrulanamadı.');
        }
        $year=(int)$m[1];
        $candidates=array_values(array_filter($data['series']??[],static fn(array $series): bool =>
            ($series['active']??false)===true && ($series['archive']??null)===false
            && ($series['internet']??null)===false && ($series['year']??null)===$year
            && !in_array($series['code']??null,['ABC','GIB'],true)
        ));
        // Sentinel means default serial assignment later, not a fiscal number.
        // Require an unambiguous eligible ordinary series in this narrow flow.
        if ($invoiceId!==UblSaleCandidate::EDM_SERIAL_SENTINEL) {
            if (substr($invoiceId,3,4)!==(string)$year) throw new DomainException('Belge numarası yılı uyuşmuyor.');
            $candidates=array_values(array_filter($candidates,static fn(array $series): bool =>
                $series['code']===substr($invoiceId,0,3)));
        }
        if (count($candidates)!==1) throw new DomainException('EDM aktif e-Fatura serisi belirsiz veya bulunamadı.');
        $this->unchanged($account);
        $recipient=$this->client->taxpayer($account->username,$account->password,$buyer);
        $data=$recipient['data']??null;
        if (($recipient['status']??null)!=='ok' || !is_array($data)
            || ($data['unverified_records']??0)!==0
            || !in_array($receiverAlias,$data['aliases']??[],true)) {
            throw new DomainException('EDM alıcı posta kutusu doğrulanamadı. Belge türü otomatik değiştirilemez.');
        }
        $this->unchanged($account);
    }

    private function unchanged(EdmSetting $account): void
    {
        if (!EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists()) {
            throw new DomainException('EDM hesabı değişti.');
        }
    }
}
