<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\PaytrPaymentAttempt;
use App\Support\PaytrTestAccess;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\AddressValidation;
use App\Support\CategoryHierarchy;
use App\Support\ShoppingCart;
use App\Support\OrderTracking;
use App\Support\TurkeyProvinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Check the original cart before snapshot() clamps quantities because
        // the pending PAYTR order has already reserved those same items.
        $lastOrderId = $request->session()->get('checkout_last_order');
        if ($lastOrderId
            && $request->session()->get('paytr_pending_cart') === ShoppingCart::raw($request)) {
            $pendingOrder = Order::query()->find($lastOrderId);
            if ($pendingOrder?->payment_method === 'paytr'
                && $pendingOrder->status === 'pending_payment') {
                return redirect()->route('checkout.paytr.frame', $pendingOrder);
            }
        }

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
            'bankAccountsAvailable' => BankAccount::query()->where('is_active', true)->exists(),
            'paytrAvailable' => PaytrTestAccess::available(),
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        AddressValidation::normalizePhoneInput($request);
        $paytrAvailable = PaytrTestAccess::available();
        $validated = $request->validate(array_merge(AddressValidation::rules(), [
            'email' => ['required','email','max:255'],
            'order_note' => ['nullable','string','max:2000'],
            'checkout_token' => ['required','uuid'],
            'payment_method' => ['required', Rule::in($paytrAvailable ? ['bank_transfer', 'paytr'] : ['bank_transfer'])],
            'save_address' => ['nullable','boolean'],
            'create_account' => ['nullable','boolean'],
            'account_password' => ['required_if:create_account,1','nullable',Password::min(8)],
        ]));

        $token = $validated['checkout_token'];
        if (hash_equals((string) $request->session()->get('checkout_last_token', ''), $token)
            && $request->session()->has('checkout_last_order')) {
            $previous = Order::query()->find($request->session()->get('checkout_last_order'));
            if ($previous?->payment_method === 'paytr' && $previous->status === 'pending_payment') {
                return redirect()->route('checkout.paytr.frame', $previous);
            }
            if ($previous?->payment_method === 'paytr' && $previous->status === 'cancelled') {
                return redirect()->route('checkout.index')->with('error', 'Önceki test ödemeniz başarısız. Yeniden deneyebilirsiniz.');
            }
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
        $method = $validated['payment_method'];
        $cartSnapshot = ShoppingCart::raw($request);
        $order = DB::transaction(function () use ($request, $validated, $address, $token, $user, $method, &$newUser) {
            // Stock is rechecked under row locks and never derived from the client's
            // subtotal. Stable ID ordering prevents deadlocks between checkout requests.
            $raw = ShoppingCart::raw($request);
            if (!is_array($raw) || count($raw) < 1 || count($raw) > 100) {
                throw ValidationException::withMessages(['cart' => 'Sepetiniz boş veya geçersiz.']);
            }

            $cartKeys = array_keys($raw);
            $ids = [];
            foreach ($cartKeys as $key) {
                $parts = ShoppingCart::parseKey($key);
                if (! $parts) {
                    throw ValidationException::withMessages(['cart' => 'Sepette geçersiz bir ürün var.']);
                }
                $ids[] = $parts[0];
            }
            $ids = array_values(array_unique($ids));
            // All gram-level stock rows are locked once, regardless of how many
            // hook sizes of a single product the customer selected.
            $products = Product::query()->with(['category', 'options'])->whereIn('id', $ids)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($ids)) {
                throw ValidationException::withMessages(['cart' => 'Ürünlerden biri artık mevcut değil.']);
            }

            $allCategories = CategoryHierarchy::all();
            $lines = [];
            $reserved = [];
            $subtotal = 0;
            foreach ($raw as $key => $rawQty) {
                [$id, $optionId] = ShoppingCart::parseKey($key);
                $product = $products->get($id);
                $option = $optionId ? $product?->options->firstWhere('id', $optionId) : null;
                $qty = (int) $rawQty;

                if (! is_numeric($rawQty) || $qty != $rawQty || $qty < 1 || $qty > 999
                    || ! $product?->is_active || ! CategoryHierarchy::visible($product->category, $allCategories)
                    || ($product->options->isNotEmpty() && ! $option)
                    || ($product->options->isEmpty() && $optionId !== null)) {
                    throw ValidationException::withMessages([
                        'cart' => ($product?->name ?? 'Seçili ürün').' için seçenek veya adet değişti. Sepetinizi kontrol edin.',
                    ]);
                }

                $reserved[$id] = (int) ($reserved[$id] ?? 0) + $qty;
                if ($reserved[$id] > min(999, $product->stock_quantity)) {
                    throw ValidationException::withMessages([
                        'cart' => $product->name.' için toplam stok miktarı yeterli değil.',
                    ]);
                }
                $unitCents = (int) round((float) $product->price * 100);
                $optionLabel = $option ? $product->option_label : null;
                $optionValue = $option?->value;
                $lines[] = compact('product', 'qty', 'unitCents', 'optionLabel', 'optionValue');
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
                'payment_method' => $method,
                'status' => 'pending_payment',
                'subtotal_cents' => $subtotal,
                'shipping_cents' => $shipping,
                'total_cents' => $subtotal + $shipping,
                'currency' => 'TRY',
                'is_test' => (bool) config('newtic.checkout_test_mode', true),
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
                    'option_label' => $line['optionLabel'],
                    'option_value' => $line['optionValue'],
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitCents,
                    'line_total_cents' => $unitCents * $quantity,
                ]);
                $product->decrement('stock_quantity', $quantity);
            }

            if ($method === 'paytr') {
                // Attempt is saved atomically with the order and stock reservation.
                PaytrPaymentAttempt::createForOrder($order);
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
        if ($method === 'paytr') {
            // Leave the cart intact if PayTR rejects the test transaction.
            // A paid order clears only its unchanged original cart on return.
            $request->session()->put('paytr_pending_cart', $cartSnapshot);

            return redirect()->route('checkout.paytr.frame', $order);
        }

        ShoppingCart::clear($request);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Request $request, Order $order): View
    {
        $isOwner = $request->user() && $order->user_id === $request->user()->id;
        $isSessionOrder = in_array($order->id, (array) $request->session()->get('confirmed_order_ids', []), true);
        abort_unless($isOwner || $isSessionOrder || $request->hasValidSignature(false), 404);

        $order->load(['items', 'paymentNotifications', 'paytrAttempts']);
        if ($order->payment_method === 'paytr'
            && in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true)
            && $isSessionOrder
            && $request->session()->get('paytr_pending_cart') === ShoppingCart::raw($request)) {
            ShoppingCart::clear($request);
            $request->session()->forget('paytr_pending_cart');
        }
        $bankAccounts = $order->payment_method === 'bank_transfer' && $order->status === 'pending_payment'
            ? BankAccount::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $latestPaymentNotification = $order->paymentNotifications->first();
        $tracking = OrderTracking::for($order, $latestPaymentNotification);
        $paytrAttempt = $order->paytrAttempts->first();

        // A capability-style guest link permits returning after the browser
        // session expires. Sign the relative URL to survive reverse proxies.
        $expires = now()->addDays(14);
        $paymentReturnUrl = url(URL::temporarySignedRoute(
            'checkout.success', $expires, ['order' => $order->id], absolute: false
        ));
        $paymentNotificationAction = url(URL::temporarySignedRoute(
            'checkout.payment-notify', $expires, ['order' => $order->id], absolute: false
        ));

        return view('store.order-success', compact(
            'order', 'bankAccounts', 'latestPaymentNotification',
            'paymentReturnUrl', 'paymentNotificationAction', 'tracking', 'paytrAttempt'
        ));
    }
}
