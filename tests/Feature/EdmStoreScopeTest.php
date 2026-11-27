<?php

namespace Tests\Feature;

use App\Services\Invoices\EdmArchiveSeriesReadiness;
use App\Services\Invoices\EdmStoreScope;
use App\Services\Invoices\InvoiceDispatch;
use Tests\TestCase;

final class EdmStoreScopeTest extends TestCase
{
    public function test_domain_is_derived_from_https_installation_and_not_request(): void
    {
        config(['newtic.checkout_test_mode'=>true]);
        foreach (['https://polariskursun.com','https://sinaydesign.com/','https://new-store.example.test'] as $url) {
            config(['app.url'=>$url]);
            $this->assertSame(rtrim($url,'/'),EdmStoreScope::website());
            $this->assertTrue(InvoiceDispatch::staging());
        }
        config(['newtic.checkout_test_mode'=>false]);
        $this->assertSame('https://new-store.example.test',EdmStoreScope::website());
        $this->assertFalse(InvoiceDispatch::staging());
    }

    public function test_invalid_installation_url_cannot_enable_sandbox_order_transport(): void
    {
        config(['newtic.checkout_test_mode'=>true]);
        foreach (['http://shop.example.test','https://user:pass@shop.example.test','https://shop.example.test/path',
            'https://shop.example.test?x=1','https://shop.example.test#foo','https://127.0.0.1',
            'https://shop.example.test:8443',''] as $url) {
            config(['app.url'=>$url]);
            $this->assertNull(EdmStoreScope::website());
            $this->assertFalse(InvoiceDispatch::staging());
        }
    }

    public function test_archive_series_readiness_does_not_authorize_submission(): void
    {
        $row=fn($code,$year,$active,$archive,$internet)=>compact('code','year','active','archive','internet');
        $data=['status'=>'ok','data'=>['unreadable_series'=>0,'series'=>[
            $row('A01',2026,true,false,false),$row('EA1',2026,true,true,true),
            $row('EA2',2025,true,true,true),$row('EA3',2026,false,true,true),
            $row('EA4',2026,true,true,false),$row('ABC',2026,true,true,true),
        ]]];
        $this->assertSame(['state'=>'available','series'=>['EA1'],'submission_allowed'=>false],EdmArchiveSeriesReadiness::inspect($data,2026));
        $this->assertSame('not_found',EdmArchiveSeriesReadiness::inspect($data,2027)['state']);
        $data['data']['unreadable_series']=1;
        $this->assertSame(['state'=>'partial','series'=>['EA1'],'submission_allowed'=>false],EdmArchiveSeriesReadiness::inspect($data,2026));
        $data['data']['series'][]=$row('EA1',2026,false,true,true);
        $this->assertSame(['state'=>'unverified','series'=>[],'submission_allowed'=>false],EdmArchiveSeriesReadiness::inspect($data,2026));
        $this->assertSame('unverified',EdmArchiveSeriesReadiness::inspect(['status'=>'failed'],2026)['state']);
    }
}
