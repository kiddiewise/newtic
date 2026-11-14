<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ShippingProvider;
use App\Models\ShippingShipment;
use App\Services\ShippingShipmentManager;
use App\Support\ShippingTestAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ShippingCallbackController extends Controller
{
    public function __invoke(Request $request, ShippingShipmentManager $manager): Response
    {
        // No browser session/CSRF, instead use the provider's documented
        // HMAC-SHA512 over shipment_id+status+time and a fixed staging host.
        if (!ShippingTestAccess::allowed()) return response('inactive', 503);

        $payload = $request->json()->all();
        $id = $payload['shipment_id'] ?? null;
        $status = $payload['status'] ?? null;
        $time = $payload['time'] ?? null;
        $hash = $payload['hash'] ?? null;

        if (!filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || !is_string($status) || !in_array($status, ShippingShipmentManager::STATUSES, true)
            || !is_string($time) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $time)
            || !is_string($hash) || !preg_match('/^[a-f0-9]{128}$/Di', $hash)) {
            return response('invalid', 422);
        }
        $provider = ShippingProvider::where('provider','kargoentegrator')->first();
        if (!$provider?->hasKey()) return response('inactive', 503);

        $expected = hash_hmac('sha512', (string)$id.$status.$time, $provider->api_key);
        if (!hash_equals($expected, strtolower($hash))) {
            return response('unauthorized', 401);
        }

        $shipment = ShippingShipment::query()
            ->where('shipping_provider_id', $provider->id)
            ->where('remote_id', (int)$id)->first();
        if (!$shipment || !$shipment->carrier->is_dummy) return response('not found', 404);
        $manager->acceptCallback($shipment, $status, $time);

        return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store');
    }
}
