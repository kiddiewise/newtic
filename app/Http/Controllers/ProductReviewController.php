<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Support\CategoryHierarchy;
use App\Support\ProductReviewEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->category?->is_active
            && CategoryHierarchy::visible($product->category), 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'rating.required' => 'Lütfen 1 ile 5 arasında yıldız seçin.',
            'rating.between' => 'Lütfen geçerli bir yıldız puanı seçin.',
            'body.required' => 'Ürün deneyiminizi birkaç cümleyle anlatın.',
            'body.min' => 'Yorumunuz en az 15 karakter olmalı.',
            'body.max' => 'Yorumunuz en fazla 1000 karakter olabilir.',
        ]);

        $order = ProductReviewEligibility::order($request->user(), $product);
        if (! $order) {
            return back()->withErrors([
                'review' => 'Yorum yazabilmek için bu ürünü hesabınızla satın almış ve siparişinizi tamamlamış olmanız gerekir.',
            ])->withFragment('urun-yorumlari');
        }

        $existing = ProductReview::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)->first();

        // An edited review must be moderated again, including formerly
        // approved reviews. Only one review per customer and gram/SKU.
        ProductReview::query()->upsert([[
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'rating' => (int) $data['rating'],
            'body' => trim($data['body']),
            'status' => 'pending',
            'is_test' => (bool) $order->is_test,
            'moderated_at' => null,
            'moderated_by' => null,
            'created_at' => $existing?->created_at ?? now(),
            'updated_at' => now(),
        ]], ['product_id', 'user_id'], [
            'order_id', 'rating', 'body', 'status', 'is_test',
            'moderated_at', 'moderated_by', 'updated_at',
        ]);

        return redirect()->route('product.show', $product->slug)
            ->withFragment('urun-yorumlari')
            ->with('review_success', $existing
                ? 'Değerlendirmeniz güncellendi. Yeniden onaylandıktan sonra yayınlanacak.'
                : 'Teşekkürler! Değerlendirmeniz incelendikten sonra yayınlanacak.');
    }
}
