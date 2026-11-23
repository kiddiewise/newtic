<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OrderDocumentController extends Controller
{
    public function download(Request $request, Order $order, string $mode, OrderDocumentService $documents): Response
    {
        abort_unless(in_array($mode, ['proforma', 'packing'], true), 404);

        $data = $documents->render($order, $mode);
        $filename = 'NEWTIC-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $order->order_number)
            .'-'.($mode === 'proforma' ? 'Promofatura' : 'Hazirlama-Formu').'.pdf';
        $disposition = $request->query('preview') === '1' ? 'inline' : 'attachment';

        return response($data, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($data),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
