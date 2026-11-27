<?php
namespace App\Services\Invoices;

use App\Models\EdmSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class EdmStoredFault
{
    /** Only two fixed synthetic trials, current account, staging, no remote calls. */
    public static function read(string $uuid,?EdmSetting $account): array
    {
        if (!InvoiceDispatch::staging()||!$account?->configured()||!$account->revision||
            !in_array($uuid,[EdmSandboxSmokeFixture::UUID,EdmSecondSandboxFixture::UUID],true)) return [];
        $row=DB::table('edm_sandbox_trials')->where('uuid',$uuid)->where('account_revision',$account->revision)->first();
        if (!$row||!$row->fault_detail_ciphertext) return [];
        try {
            $data=json_decode(Crypt::decryptString($row->fault_detail_ciphertext),true,32,JSON_THROW_ON_ERROR);
            $out=[];
            foreach (['ERROR_SHORT_DES','ERROR_LONG_DES'] as $key) {
                $value=$data[$key]??null;
                if (!is_string($value)||strlen($value)>16384) continue;
                // Exact known credentials redacted again before private display.
                foreach ([$account->username,$account->password] as $secret) {
                    if (is_string($secret)&&$secret!=='') $value=str_replace($secret,'[REDACTED]',$value);
                }
                $value=preg_replace('~(?i)(session[\s_-]*id|password|şifre|parola|authorization|bearer|user[\s_-]*name)\s*[:=]\s*[^;\r\n,]+~u','$1: [REDACTED]',$value)??'[REDACTED]';
                $out[$key]=$value;
            }
            return $out;
        } catch (\Throwable) {return [];}
    }

    /** Public CI output: static vocabulary ONLY, never provider text. */
    public static function classify(array $data): array
    {
        $text=implode("\n",array_filter($data,'is_string'));
        if (trim($text)==='') return ['description_unavailable'];
        $patterns=[
            'dotnet_null_reference'=>'~object reference not set|object reference|nesne başvurusu|nesne referansı|nullreferenceexception~iu',
            'dotnet_empty_sequence'=>'~sequence contains no elements|sequence contains no matching|dizi hiçbir öğe|eşleşen öğe|invalidoperationexception~iu',
            'dotnet_format'=>'~input string was not in a correct format|format exception|formatexception|giriş dizesi|incorrect format~iu',
            'dotnet_sql'=>'~sql(exception| server)|database|veritabanı|unique constraint|foreign key|deadlock|stored procedure|violation of~iu',
            'dotnet_argument'=>'~argumentnullexception|argumentexception|value cannot be null|parameter name|değer null olamaz|argument out of range~iu',
            'dotnet_index'=>'~index was outside|indexoutofrange|index was out of range|dizin sınır~iu',
            'dotnet_access'=>'~access denied|access to the path|unauthorizedaccessexception|erişim engellendi~iu',
            'dotnet_file'=>'~file not found|could not find file|filenotfoundexception|directorynotfound|dosya bulunamadı~iu',
            'dotnet_timeout'=>'~timeout|timed out|zaman aşımı~iu',
            'xml_deserialization'=>'~error in xml document|deserialize|deserialization|root element is missing|data at the root level|xml document|xml belgesinde~iu',
            'xml_encoding'=>'~base64|encoding|invalid character|geçersiz karakter|kodlama~iu',
            'xml_extension'=>'~extensioncontent|ublextensions|signature_~iu',
            'xml_schema'=>'~schema|schematron|xsd|şema|sematron~iu',
            'xslt'=>'~xslt|stylesheet|şablon|template|tasarım~iu',
            'serial'=>'~serial|seri|numara|invoiceid|generateinvoiceidonload~iu',
            'party'=>'~sender|receiver|alias|vkn|tckn|gönderici|alıcı|etiket|posta kutusu~iu',
            'profile'=>'~profileid|temelfatura|ticarifatura|senaryo|earsivfatura~iu',
            'signature'=>'~imza|signature|mühür|xades~iu',
            'permission'=>'~yetki|permission|unauthorized|forbidden|izin~iu',
            'duplicate'=>'~duplicate|already exists|mükerrer|daha önce|zaten~iu',
            'date'=>'~issuedate|tarih|date~iu',
            'amount'=>'~amount|tutar|kdv|tax|vergi~iu',
            'provider_generic_load_failure'=>'~yüklenemedi|load failed|could not load|cannot load~iu',
            'license_or_credit'=>'~kontör|credit|license|lisans~iu',
        ];
        $out=[];
        foreach($patterns as $code=>$pattern) if(preg_match($pattern,$text)===1) $out[]=$code;
        // Only explicit known documented fault codes; no arbitrary numerics.
        foreach ([11019,11020,11029,11030,11040,11046,11048,11056,11090,11096] as $code)
            if(preg_match('~(?<![0-9])'.$code.'(?![0-9])~',$text)===1) $out[]='nested_EDM_'.$code;
        return $out?:['description_unclassified'];
    }
}
