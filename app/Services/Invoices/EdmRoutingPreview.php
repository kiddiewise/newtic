<?php

namespace App\Services\Invoices;

use App\Models\EdmSetting;
use DomainException;

/**
 * Read-only routing evidence for an explicitly selected ordinary invoice
 * test route. Never authorizes loading or allocates an invoice number.
 * Unlike the strict submission gate, reports all independently observed
 * checks, including unknown rows; no raw provider response is exposed.
 */
final class EdmRoutingPreview
{
    public function __construct(private EdmTestClient $client) {}

    public function inspect(EdmSetting $account, #[\SensitiveParameter] string $seller,
        #[\SensitiveParameter] string $buyer, #[\SensitiveParameter] string $gb,
        #[\SensitiveParameter] string $pk, string $series, int $year): array
    {
        if (!$account->configured() || !$account->revision || $account->probe_status !== 'ok'
            || !preg_match('/^[A-Z0-9]{3}$/D',$series) || in_array($series,['ABC','GIB'],true)
            || $year<2000 || $year>2099) throw new DomainException('EDM ön kontrol verileri geçersiz.');
        foreach ([$seller,$buyer] as $id) {
            if (!preg_match('/^[0-9]{10,11}$/D',$id) || $id==='11111111111') throw new DomainException('EDM taraf kimliği geçersiz.');
        }
        foreach ([$gb,$pk] as $alias) {
            if (!preg_match('/^urn:mail:[^\s<>]{1,250}$/Di',$alias)) throw new DomainException('EDM etiketi geçersiz.');
        }
        $this->unchanged($account);
        $company=$this->client->company($account->username,$account->password,$seller);
        $this->unchanged($account);
        $recipient=$this->client->taxpayer($account->username,$account->password,$buyer);
        $this->unchanged($account);
        $companyOk=($company['status']??null)==='ok' && is_array($company['data']??null);
        $recipientOk=($recipient['status']??null)==='ok' && is_array($recipient['data']??null);
        $companyData=$companyOk?$company['data']:[];
        $recipientData=$recipientOk?$recipient['data']:[];
        $matches=array_values(array_filter($companyData['series']??[],static fn(array $row): bool =>
            ($row['code']??null)===$series && ($row['year']??null)===$year));
        $eligible=count($matches)===1 && $matches[0]['active']===true && $matches[0]['archive']===false && $matches[0]['internet']===false;
        $unreadable=$companyOk?(int)($companyData['unreadable_series']??0):null;
        $unverified=$recipientOk?(int)($recipientData['unverified_records']??0):null;
        $checks=[
            'company'=>$companyOk,
            'sender_alias'=>$companyOk?($companyData['gb']??null)===$gb:null,
            'series'=>$companyOk?$eligible:null,
            'series_integrity'=>$companyOk?$unreadable===0:null,
            'recipient'=>$recipientOk,
            'recipient_alias'=>$recipientOk?in_array($pk,$recipientData['aliases']??[],true):null,
            'recipient_integrity'=>$recipientOk?$unverified===0:null,
        ];
        return ['checks'=>$checks,'unreadable_series'=>$unreadable,'unverified_records'=>$unverified,
            'all_checks_passed'=>!in_array(false,$checks,true)&&!in_array(null,$checks,true),
            'submission_allowed'=>false];
    }

    private function unchanged(EdmSetting $account): void
    {
        if (!EdmSetting::query()->whereKey(1)->where('revision',$account->revision)->where('probe_status','ok')->exists()) {
            throw new DomainException('EDM hesabı değişti.');
        }
    }
}
