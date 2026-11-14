<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingExceptionReview;
use App\Models\ShippingShipment;
use App\Services\ShippingReconciliationService;
use App\Support\ShippingStatus;
use App\Support\ShippingTestAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ShippingOperationsController extends Controller
{
    private const FILTERS = ['attention','all','moving','delivered','reviewed','sync_error'];

    public function index(Request $request): View
    {
        $filter = (string)$request->query('filter', 'attention');
        abort_unless(in_array($filter, self::FILTERS, true), 422);
        $term = trim((string)$request->query('q', ''));
        abort_if(mb_strlen($term) > 100, 422);

        $query = ShippingShipment::query()
            ->with(['order','carrier','warehouse','exceptionReviews.user'])
            ->when($term !== '', fn($q) => $q->whereHas('order', fn($orders) =>
                $orders->where('order_number', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($filter === 'attention', fn($q) => $q->where(function ($needs) {
                $needs->where('state', 'uncertain')
                    ->orWhere('sync_failure_count', '>=', 2)
                    ->orWhere(function ($exceptions) {
                        $exceptions->whereIn('remote_status', ShippingStatus::EXCEPTIONS)
                            ->whereNull('exception_acknowledged_at');
                    });
            }))
            ->when($filter === 'moving', fn($q) => $q->where('state', 'submitted')
                ->whereNotIn('remote_status', array_merge(ShippingStatus::EXCEPTIONS, ['delivered'])))
            ->when($filter === 'delivered', fn($q) => $q->where('remote_status', 'delivered'))
            ->when($filter === 'reviewed', fn($q) => $q->whereNotNull('exception_acknowledged_at'))
            ->when($filter === 'sync_error', fn($q) => $q->where('sync_failure_count', '>', 0));

        $shipments = $query->latest('id')->paginate(20)->withQueryString();
        $counts = [
            'all' => ShippingShipment::count(),
            'attention' => ShippingShipment::where(function ($q) {
                $q->where('state', 'uncertain')
                    ->orWhere('sync_failure_count', '>=', 2)
                    ->orWhere(function ($e) {
                        $e->whereIn('remote_status', ShippingStatus::EXCEPTIONS)
                            ->whereNull('exception_acknowledged_at');
                    });
            })->count(),
            'moving' => ShippingShipment::where('state','submitted')
                ->whereNotIn('remote_status', array_merge(ShippingStatus::EXCEPTIONS, ['delivered']))->count(),
            'delivered' => ShippingShipment::where('remote_status','delivered')->count(),
            'reviewed' => ShippingShipment::whereNotNull('exception_acknowledged_at')->count(),
            'sync_error' => ShippingShipment::where('sync_failure_count', '>', 0)->count(),
        ];

        return view('admin.shipping-integrations.operations', compact(
            'shipments','counts','filter','term'
        ));
    }

    /**
     * Force a bounded read-only recovery pass. No carrier POST, cancel or refund.
     */
    public function sync(ShippingReconciliationService $service): RedirectResponse
    {
        abort_unless(ShippingTestAccess::allowed(), 403);
        $result = $service->run(20, true);
        return redirect()->route('admin.shipping-operations.index')
            ->with('success', sprintf(
                'Kargo kontrolü: %d sorgu, %d başarılı, %d başarısız, %d atlandı. '
                .'Yalnızca Dummy Kargo kayıtları okundu.',
                $result['checked'], $result['updated'], $result['failed'], $result['skipped']
            ));
    }

    public function review(Request $request, ShippingShipment $shipment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required','string','min:15','max:500'],
        ]);

        DB::transaction(function () use ($request,$shipment,$data): void {
            $locked = ShippingShipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if (!ShippingStatus::isException($locked->remote_status)
                && $locked->state !== 'uncertain'
                && $locked->sync_failure_count < 2) {
                throw ValidationException::withMessages([
                    'shipping' => 'Bu kargo kaydı için henüz inceleme gerektiren bir sorun bulunmuyor.',
                ]);
            }
            if ($locked->exception_acknowledged_at
                && $locked->exception_acknowledged_status === $locked->remote_status) {
                throw ValidationException::withMessages([
                    'shipping' => 'Bu kargo durumu zaten incelendi. Yeni gelişme olursa yeniden inceleyebilirsin.',
                ]);
            }

            $locked->exceptionReviews()->create([
                'user_id' => $request->user()->id,
                'remote_status' => $locked->remote_status,
                'reason' => trim($data['reason']),
                'created_at' => now(),
            ]);
            $locked->update([
                'exception_acknowledged_at' => now(),
                'exception_acknowledged_status' => $locked->remote_status,
                'exception_acknowledged_by' => $request->user()->id,
                'exception_note' => trim($data['reason']),
            ]);
        }, 3);

        return back()->with('success',
            'İnceleme kaydedildi. Kargo, ödeme ve stok kayıtları değiştirilmedi.');
    }
}
