<?php

namespace App\Services\Invoices;

use DateTimeInterface;
use DomainException;

/** Pure mapping of EDM v4.2.1 INVOICEHEADERINTERNETSALESDETAILS.
 * No implicit payment or carrier inference; no sending capability. */
final class EdmInternetSaleDetails
{
    public function build(string $website,string $paymentMethod,?string $intermediary,DateTimeInterface $paidAt,DateTimeInterface $dispatchedAt,string $carrierVkn,string $carrierTitle): array
    {
        $url=parse_url($website);
        if (!filter_var($website,FILTER_VALIDATE_URL) || ($url['scheme']??null)!=='https'
            || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new DomainException('İnternet satış adresi geçersiz.');
        }
        if (!in_array($paymentMethod,['KREDIKARTI/ BANKAKARTI','EFT/HAVALE','ODEMEARACISI'],true)) throw new DomainException('EDM ödeme şekli doğrulanmalıdır.');
        if ($paymentMethod==='ODEMEARACISI' && (!is_string($intermediary) || trim($intermediary)==='' || mb_strlen($intermediary)>255)) throw new DomainException('Ödeme aracısı adı gerekli.');
        if ($paymentMethod!=='ODEMEARACISI' && $intermediary!==null) throw new DomainException('Ödeme aracısı bilgisi ödeme şekliyle uyuşmuyor.');
        if (!preg_match('/^[0-9]{10}$/D',$carrierVkn) || trim($carrierTitle)==='' || mb_strlen($carrierTitle)>255) throw new DomainException('Taşıyıcı firma bilgileri gerekli.');
        if ($dispatchedAt<$paidAt) throw new DomainException('Bu ödeme doğrulamalı akışta sevk tarihi ödeme tarihinden önce olamaz.');
        $details=['webAdresi'=>$website,'odemeSekli'=>$paymentMethod];
        if ($intermediary!==null) $details['odemeAracisiAdi']=trim($intermediary);
        $details['odemeTarihi']=$paidAt->format(DATE_ATOM);
        $details['odemeTarihiSpecified']=true;
        $details['gonderiBilgileri']=[
            'gonderimTarihi'=>$dispatchedAt->format(DATE_ATOM),
            'gonderiTasiyan'=>['tuzelKisi'=>['VKN'=>$carrierVkn,'unvan'=>trim($carrierTitle)]],
        ];
        return ['INTERNETSALES'=>true,'EARCHIVE'=>true,'INTERNETSALESDETAILS'=>$details];
    }
}
