<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        abort_unless(in_array($status, ['all', 'pending', 'approved', 'rejected'], true), 422);
        $reviews = ProductReview::query()
            ->with(['user:id,name,email', 'product:id,name,slug', 'order:id,order_number,is_test'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(15)->withQueryString();

        $counts = ProductReview::query()
            ->selectRaw('status, count(*) as total')->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.reviews.index', compact('reviews', 'status', 'counts'));
    }

    public function moderate(Request $request, ProductReview $review): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        DB::transaction(function () use ($review, $request, $data) {
            $locked = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'review' => 'Bu yorum zaten incelenmiş. Müşteri yeniden düzenlerse tekrar onaya gelir.',
                ]);
            }
            $locked->update([
                'status' => $data['decision'],
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
            ]);
        }, 3);

        return back()->with('success', $data['decision'] === 'approved'
            ? 'Yorum onaylandı. Gerçek sipariş yorumları mağazada yayınlanır.'
            : 'Yorum reddedildi.');
    }
}
