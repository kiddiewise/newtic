<?php

namespace App\Services\Invoices;

/** Distinct code namespaces; no result ever authorizes automatic submission. */
final class EdmInvoiceOutcome
{
    public static function classify(string $source, string $code): array
    {
        $safe = preg_match('/^[0-9]{4,6}$/D', $code) === 1 ? $code : null;
        $result = ['source'=>in_array($source,['edm','gib','transport'],true)?$source:'unknown',
            'code'=>$safe,'state'=>'unknown','message'=>'İşlem sonucu doğrulanamadı.',
            'action'=>'Mevcut ETTN üzerinden durumu doğrulayın. Tekrar göndermeyin.',
            'automatic_retry'=>false];
        $entry = match ($source) {
            'edm' => match ($safe) {
                '11048','11056'=>['duplicate','Belge daha önce işleme alınmış olabilir.','Mevcut ETTN kaydını sorgulayın.'],
                '11029'=>['validation','UBL XML şeması geçersiz.','XML yapısını ve XSD doğrulamasını kontrol edin.'],
                '11030'=>['validation','Belge iş kurallarına uymuyor.','Schematron doğrulamasını inceleyin.'],
                '11031','11046'=>['validation','Alıcı adı veya unvanı eksik.','Alıcı kimlik ve unvan alanlarını kontrol edin.'],
                '11019','11022','11023','11026','11027'=>['routing','Gönderici kimliği veya etiketi uyuşmuyor.','EDM firma ve gönderici etiketini doğrulayın.'],
                '11040'=>['configuration','Belge serisi bulunamadı.','EDM üzerinden etkin belge serisini doğrulayın.'],
                '11017','11025'=>['not_found','Belge veya taslak bulunamadı.','ETTN ve hesap eşleşmesini kontrol edin; yokluğu kesin kanıt saymayın.'],
                '11060','11061'=>['validation','İnternet satış beyanı veya detayları eksik.','İnternet satış alanlarını kontrol edin.'],
                '11062','11063'=>['validation','Satış sitesi adresi eksik.','Gerçek satış adresini kontrol edin.'],
                '11064'=>['validation','Ödeme yöntemi eksik.','Siparişin ödeme yöntemini doğrulayın.'],
                '11065'=>['validation','Sevk tarihi eksik.','Gerçek sevk kaydını kontrol edin.'],
                '11066','11067','11068','11069','11070','11071'=>['validation','Taşıyıcı bilgisi eksik veya hatalı.','Taşıyıcı kimlik türünü ve bilgilerini kontrol edin.'],
                '11072','11073','11074'=>['validation','Ödeme tarihi bilgisi eksik.','Ödeme tarihi ve belirtilme alanını kontrol edin.'],
                '10011','11007','11075'=>['authentication','EDM oturum veya etiket sorunu.','Hesap ve etiketleri doğrulayın; önce mevcut ETTN durumunu sorgulayın.'],
                default=>null,
            },
            'gib'=>match ($safe) {
                '1000','1100','1200','1210','1220'=>['pending','Zarf işlemi henüz sonuçlanmadı.','Durum sorgulaması yapın; yeniden göndermeyin.'],
                '1300'=>['envelope_completed','GİB zarf süreci tamamlandı.','Yeniden göndermeyin; ticari yanıt ayrı kontrol edilir.'],
                '1163'=>['duplicate','Mükerrer fatura uyarısı.','Önceki zarfın durumunu doğrulayın.'],
                '1150','1160'=>['validation','Zarf doğrulamasında hata.','Hatanın GİB mi alıcı yanıtı mı olduğunu doğrulayın.'],
                '1215','1230'=>['delivery_failed','Zarf teslimi başarısız.','Mevcut belgeyi ve zarfı inceleyin; kontrollü yeniden gönderim için operatör kararı gerekir.'],
                '1171','1172'=>['routing','Zarf etiket yetkisi hatası.','Gönderici ve alıcı etiketlerini doğrulayın.'],
                default=>null,
            },
            default=>null,
        };
        if ($entry !== null) [$result['state'],$result['message'],$result['action']]=$entry;
        return $result;
    }
}
