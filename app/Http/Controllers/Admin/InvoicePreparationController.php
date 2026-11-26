<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoicePreparation;
use App\Models\Order;
use App\Services\Invoices\InvoicePreparationService;
use App\Services\Invoices\InvoicePreparationXml;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class InvoicePreparationController extends Controller
{
    public function store(Request $request,Order $order,InvoicePreparationService $service): RedirectResponse
    {
        $data=$request->validate(['shipping_rate'=>['nullable','integer','in:1,10,20']]);
        $rate=isset($data['shipping_rate']) ? (int)$data['shipping_rate'] : null;
        $back=redirect()->route('admin.orders.invoice-preview',array_filter(['order'=>$order,'shipping_rate'=>$rate],fn($v)=>$v!==null));
        try {
            $service->prepare($order,$rate);
        } catch (DomainException $e) {
            return $back->withErrors(['invoice'=>$e->getMessage()]);
        }
        return $back->with('success','Yerel teknik taslak hazır. EDM gönderimi yapılmadı.');
    }

    public function xml(Order $order,InvoicePreparationXml $xml): Response
    {
        $draft=InvoicePreparation::query()->where('order_id',$order->getKey())->firstOrFail();
        try {
            $body=$xml->render($draft);
        } catch (DomainException) {
            abort(409,'Taslak doğrulanamadı.');
        }
        return response($body,200,[
            'Content-Type'=>'application/xml; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="newtic-teknik-taslak-'.$draft->uuid.'.xml"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
