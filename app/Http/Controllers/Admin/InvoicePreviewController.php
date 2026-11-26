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
        $preview = null;
        $error = null;
        try {
            $preview = $amounts->preview($order,$rate===null ? null : (int)$rate);
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
        return response()->view('admin.orders.invoice-preview', [
            'order'=>$order,'preview'=>$preview,'calculationError'=>$error,'shippingRate'=>$rate,
            'payment'=>OrderPaymentBreakdown::fromOrder($order),
        ])->header('Cache-Control','private, no-store, max-age=0');
    }
}
