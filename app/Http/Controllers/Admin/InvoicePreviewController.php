<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Invoices\InvoiceAmounts;
use App\Support\OrderPaymentBreakdown;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InvoicePreviewController extends Controller
{
    public function __invoke(Request $request, Order $order, InvoiceAmounts $amounts): Response
    {
        $rate = $request->query('shipping_rate');
        if ($rate !== null && (!is_string($rate) || !in_array($rate,['1','10','20'],true))) abort(422);
        if ($rate===null) {
            $saved=\App\Models\InvoicePreparation::query()->where('order_id',$order->id)->first();
            $configured=\App\Models\EdmSetting::query()->find(1)?->shipping_vat_rate;
            $rate=$saved?->shipping_rate!==null?(string)$saved->shipping_rate:($configured!==null?(string)$configured:null);
        }
        $order->loadMissing('items.product');
        $missingItems=$order->items->filter(fn($item)=>$item->vat_rate===null);
        $preview = null;
        $error = null;
        try {
            $preview = $amounts->preview($order,$rate===null ? null : (int)$rate);
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
        return response()->view('admin.orders.invoice-preview', [
            'order'=>$order,'missingItems'=>$missingItems,'preview'=>$preview,'calculationError'=>$error,'shippingRate'=>$rate,
            'preparation'=>\App\Models\InvoicePreparation::query()->where('order_id',$order->getKey())->first(),
            'payment'=>OrderPaymentBreakdown::fromOrder($order),
        ])->header('Cache-Control','private, no-store, max-age=0');
    }
}
