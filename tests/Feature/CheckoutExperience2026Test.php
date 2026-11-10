<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutExperience2026Test extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Surf Kurşun', 'slug' => 'surf-kursun', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Renkli Surf Kurşun 75 gr',
            'sku' => 'UX-75',
            'slug' => 'renkli-surf-75-ux',
            'price' => '149.90',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function start(Product $product): string
    {
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])
            ->assertRedirect(route('cart.index'));
        $this->get(route('checkout.index'))->assertOk();

        return (string) session('checkout_token');
    }

    private function payload(string $token, array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'email' => 'deneme@example.com',
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '+90 532 123 45 67',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi Mustafa Kemal Sokak No 12',
            'invoice_type' => 'individual',
        ], $overrides);
    }

    public function test_guest_checkout_has_visible_guest_option_and_mobile_order_review(): void
    {
        $product = $this->product();
        $this->start($product);

        $html = $this->get(route('checkout.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Misafir olarak devam et', $html);
        $this->assertStringContainsString('Üyelik gerekmiyor', $html);
        $this->assertStringContainsString('checkout-mobile-review', $html);
        $this->assertStringContainsString('Sipariş özetini gör', $html);
        $this->assertStringContainsString('checkout-steps', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
        $this->assertStringContainsString('checkout-order-summary', $html);
        $this->assertStringNotContainsString('Güvenli bağlantı">♧', $html);
    }

    public function test_phone_country_code_and_spacing_are_normalized_before_creating_order(): void
    {
        $product = $this->product();
        $token = $this->start($product);

        $this->post(route('checkout.place'), $this->payload($token))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('05321234567', $order->phone);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_address_book_accepts_a_mobile_number_with_spaces(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('account.addresses.store'), [
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '0532 123 45 67',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi Mustafa Kemal Sokak No 12',
            'invoice_type' => 'individual',
        ])->assertRedirect(route('account.addresses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_addresses', ['phone' => '05321234567']);
    }

    public function test_checkout_invalid_fields_have_inline_accessible_messages(): void
    {
        $product = $this->product();
        $token = $this->start($product);

        $response = $this->followingRedirects()->post(route('checkout.place'), $this->payload($token, [
            'email' => 'not-an-email',
            'phone' => '12345',
        ]));

        $response->assertOk()
            ->assertSee('checkout-error-email')
            ->assertSee('checkout-error-phone')
            ->assertSee('aria-invalid="true"', false);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_and_address_errors_are_localized_in_turkish(): void
    {
        app()->setLocale('tr');

        $this->assertSame(
            'Cep telefonu alanını doldurun.',
            __('validation.required', ['attribute' => 'Cep telefonu'])
        );
    }
}
