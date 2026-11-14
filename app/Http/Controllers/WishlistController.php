<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WishlistController extends Controller
{
    /** Favorites are owned by the logged-in account, never by an anonymous session. */
    public static function ids(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        return DB::table('wishlist_items')
            ->where('user_id', $request->user()->id)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Complete a favoriting action requested before the customer signed in. */
    public static function addForUser(User $user, int $productId): bool
    {
        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $product) {
            return false;
        }

        $query = DB::table('wishlist_items')->where('user_id', $user->id);
        if ($query->where('product_id', $product->id)->exists()) {
            return true;
        }

        if (DB::table('wishlist_items')->where('user_id', $user->id)->count() >= 100) {
            return false;
        }

        $now = now();

        DB::table('wishlist_items')->insertOrIgnore([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    public function index(Request $request): View
    {
        $ids = self::ids($request);
        $products = Product::query()->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get();

        return view('store.wishlist', compact('products'));
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);

        $userId = $request->user()->id;
        $query = DB::table('wishlist_items')
            ->where('user_id', $userId)
            ->where('product_id', $product->id);

        if ($query->exists()) {
            $query->delete();
            $message = 'Ürün favorilerden çıkarıldı.';
        } else {
            if (DB::table('wishlist_items')->where('user_id', $userId)->count() >= 100) {
                return back()->with('error', 'En fazla 100 ürün favorilere eklenebilir.');
            }

            $now = now();
            DB::table('wishlist_items')->insertOrIgnore([
                'user_id' => $userId,
                'product_id' => $product->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $message = 'Ürün favorilere eklendi.';
        }

        return back()->with('success', $message);
    }
}
