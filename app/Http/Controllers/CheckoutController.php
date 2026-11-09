<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\AddressValidation;
use App\Support\CategoryHierarchy;
use App\Support\ShoppingCart;
use App\Support\TurkeyProvinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $cart = ShoppingCart::snapshot($request);
        if (!$cart['lines']) {
            return redirect()->route('cart.index')->with('error', 'Önce sepetinize ürün ekleyin.');
        }

        if (!$request->session()->has('checkout_token')) {
            $request->session()->put('checkout_token', (string) Str::uuid());
        }

        $addresses = $request->user()
            ? $request->user()->addresses()->orderByDesc('is_default')->latest()->get()
            : collect();
        $selectedAddress = $addresses->first();
        return view('store.checkout', [
            'cart' => $cart,
            'checkoutToken' => $request->session()->get('checkout_token'),
            'addresses' => $addresses,
            'selectedAddress' => $selectedAddress,
            'provinces' => TurkeyProvinces::ALL,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge(AddressValidation::rules(), [
            'email' => ['required','email','max:255'],
            'order_note' => ['nullable','string','max:2000'],
            'checkout_token' => ['required','uuid'],
            'payment_method' => ['required',Rule::in(['bank_transfer'])],
            'save_address' => ['nullable','boolean'],
            'create_account' => ['nullable','boolean'],
            'account_password' => ['required_if:create_account,1','nullable',Password::min(8)],
        ]));

        $token = $validated['checkout_token'];
        if (hash_equals((string) $request->session()->get('checkout_last_token', ''), $token)
            && $request->session()->has('checkout_last_order')) {
            return redirect()->route('checkout.success', $request->session()->get('checkout_last_order'));
        }
        $expected = (string) $request->session()->get('checkout_token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Sipariş oturumu yenilendi. Sayfayı tekrar açın.',
            ]);
        }

        if ($request->boolean('create_account') && $request->user()) {
            throw ValidationException::withMessages(['create_account' => 'Zaten hesabınıza giriş yaptınız.']);
        }
        if ($request->boolean('create_account') &&
            User::query()->where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Bu e-posta hesabı zaten kayıtlı. Giriş yapın veya üyeliksiz devam edin.',
            ]);
        }

        $address = AddressValidation::clean($validated);
        $user = $request->user();
        $newUser = null;
        $order = DB::transaction(function () use ($request, $validated, $address, $token, $user, &$newUser) {
            // Stock is rechecked under row locks and never derived from the client's
            // subtotal. Stable ID ordering prevents deadlocks between checkout requests.
            $raw = ShoppingCart::raw($request);
            if (!is_array($raw) || count($raw) < 1 || count($raw) > 100) {
                throw ValidationException::withMessages(['cart' => 'Sepetiniz boş veya geçersiz.']);
            }

            $ids = array_keys($raw);
            foreach ($ids as $id) {
                if (!ctype_digit((string) $id) || (int) $id < 1) {
                    throw ValidationException::withMessages(['cart' => 'Sepette geçersiz bir ürün var.']);
                }
            }
            $products = Product::query()->with('category')->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['cart' => 'Ürünlerden biri artık mevcut değil.']);
            }

            $allCategories = CategoryHierarchy::all();
            $lines = [];
            $subtotal = 0;
            foreach ($ids as $id) {
                $qty = $raw[$id];
                $product = $products->get((int) $id);
                if (!is_numeric($qty) || (int) $qty != $qty || (int) $qty < 1 || (int) $qty > 999
                    || !$product->is_active || !CategoryHierarchy::visible($product->category, $allCategories)
                    || (int) $qty > $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ($product?->name ?? 'Seçili ürün').' için stok veya adet değişti. Sepetinizi kontrol edin.',
                    ]);
                }
                $qty = (int) $qty;
                $unitCents = (int) round((float) $product->price * 100);
                $lines[] = compact('product', 'qty', 'unitCents');
                $subtotal += $unitCents * $qty;
            }
            $settings = StoreSetting::current();
            $freeThreshold = (int) round((float) $settings->free_shipping_threshold * 100);
            $fee = (int) round((float) $settings->shipping_fee * 100);
            $shipping = $subtotal >= $freeThreshold ? 0 : $fee;

            $customer = $user;
            if (!$customer && $request->boolean('create_account')) {
                $customer = User::create([
                    'name' => $address['full_name'],
                    'email' => $validated['email'],
                    'password' => $validated['account_password'],
                ]);
                $newUser = $customer;
            }

            $order = Order::create([
                'order_number' => 'PK-'.now()->format('ymd').'-'.strtoupper(Str::random(9)),
                'checkout_token' => $token,
                'user_id' => $customer?->id,
                'customer_name' => $address['full_name'],
                'customer_email' => $validated['email'],
                'phone' => $address['phone'],
                'address_label' => $address['address_label'],
                'city' => $address['city'],
                'district' => $address['district'],
                'street_address' => $address['street_address'],
                'invoice_type' => $address['invoice_type'],
                'company_title' => $address['company_title'] ?? null,
                'tax_office' => $address['tax_office'] ?? null,
                'tax_number' => $address['tax_number'] ?? null,
                'order_note' => $validated['order_note'] ?? null,
                'payment_method' => 'bank_transfer',
                'status' => 'pending_payment',
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'total_cents' => $subtotal + $shipping,
                'currency' => 'TRY',
                'is_test' => str_contains((string) config('app.url'), 'hostingersite.com'),
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $quantity = $line['qty'];
                $unitCents = $line['unitCents'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitCents,
                    'line_total_cents' => $unitCents * $quantity,
                ]);
                $product->decrement('stock_quantity', $quantity);
            }

            if ($customer && ($request->boolean('save_address') || $newUser)) {
                $previous = $customer->addresses()->exists();
                $customer->addresses()->create([
                    'label' => $address['address_label'],
                    'full_name' => $address['full_name'],
                    'phone' => $address['phone'],
                    'city' => $address['city'],
                    'district' => $address['district'],
                    'street_address' => $address['street_address'],
                    'invoice_type' => $address['invoice_type'],
                    'company_title' => $address['company_title'] ?? null,
                    'tax_office' => $address['tax_office'] ?? null,
                    'tax_number' => $address['tax_number'] ?? null,
                    'is_default' => !$previous,
                ]);
            }
            return $order;
        }, 3);

        if ($newUser) {
            Auth::login($newUser);
            $request->session()->regenerate();
        }
        $request->session()->put('checkout_last_token', $token);
        $request->session()->put('checkout_last_order', $order->id);
        $request->session()->forget('checkout_token');
        $request->session()->put('confirmed_order_ids',
            array_slice(array_merge((array) $request->session()->get('confirmed_order_ids', []), [$order->id]), -10));
        ShoppingCart::clear($request);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Request $request, Order $order): View
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $isSessionOrder = in_array($order->id, (array) $request->session()->get('confirmed_order_ids', []), true);
        abort_unless($isOwner || $isSessionOrder, 404);

        $order->load('items');
        return view('store.order-success', compact('order'));
    }
}
