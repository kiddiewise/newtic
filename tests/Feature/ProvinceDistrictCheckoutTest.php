<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\TurkeyDistricts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvinceDistrictCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function sampleProduct(): Product
    {
        $category = Category::create([
            'name' => 'Kurşunlar', 'slug' => 'kursunlar', 'is_active' => true,
        ]);

        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Polaris 50 gr',
            'slug' => 'polaris-50-gr',
            'sku' => 'DISTRICT-50',
            'price' => '49.90',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
    }

    private function checkoutPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => $token,
            'payment_method' => 'bank_transfer',
            'email' => 'customer@example.com',
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '555 555 55 55',
            'city' => 'İzmir',
            'district' => 'Bornova',
            'street_address' => 'Kazımdirik Mahallesi, Üniversite Caddesi No 10',
            'invoice_type' => 'individual',
        ], $overrides);
    }

    private function startCheckout(): string
    {
        $product = $this->sampleProduct();
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();
        $this->get(route('checkout.index'))->assertOk();

        return (string) session('checkout_token');
    }

    public function test_countrywide_district_data_has_expected_count_and_correct_izmir_districts(): void
    {
        $map = TurkeyDistricts::all();
        $this->assertCount(81, $map);
        $this->assertSame(973, array_sum(array_map('count', $map)));
        $this->assertContains('Bornova', TurkeyDistricts::forProvince('İzmir'));
        $this->assertContains('Gaziemir', TurkeyDistricts::forProvince('İzmir'));
        $this->assertNotContains('İzmir', TurkeyDistricts::forProvince('İzmir'));
        $this->assertTrue(TurkeyDistricts::belongsToProvince('İzmir', 'Buca'));
        $this->assertFalse(TurkeyDistricts::belongsToProvince('Ankara', 'Bornova'));
    }

    public function test_checkout_renders_dependent_district_select_instead_of_free_text(): void
    {
        $this->startCheckout();

        $response = $this->get(route('checkout.index'))->assertOk();
        $response->assertSee('data-tr-province', false)
            ->assertSee('data-tr-district', false)
            ->assertSee('data-tr-district-map', false)
            ->assertSee('Önce il seçin')
            ->assertSee('autocomplete="off"', false)
            ->assertDontSee('type="text" name="district"', false);
    }

    public function test_chrome_autofill_city_as_district_is_rejected_by_server(): void
    {
        $token = $this->startCheckout();

        $this->post(route('checkout.place'), $this->checkoutPayload($token, [
            'city' => 'İzmir', 'district' => 'İzmir',
        ]))->assertSessionHasErrors('district');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.place'), $this->checkoutPayload($token, [
            'city' => 'Ankara', 'district' => 'Bornova',
        ]))->assertSessionHasErrors('district');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_valid_bornova_order_keeps_correct_delivery_district(): void
    {
        $token = $this->startCheckout();
        $this->post(route('checkout.place'), $this->checkoutPayload($token))
            ->assertRedirect()->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('İzmir', $order->city);
        $this->assertSame('Bornova', $order->district);
    }

    public function test_address_book_filters_districts_and_rejects_mismatched_autofill(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->checkoutPayload('not-relevant');

        $this->post(route('account.addresses.store'), array_merge($data, [
            'city' => 'İzmir', 'district' => 'İzmir',
        ]))->assertSessionHasErrors('district');
        $this->assertDatabaseCount('customer_addresses', 0);

        $this->post(route('account.addresses.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $saved = CustomerAddress::firstOrFail();

        $this->get(route('account.addresses.edit', $saved))->assertOk()
            ->assertSee('value="Bornova" selected', false)
            ->assertSee('data-tr-district-map', false);

        $this->patch(route('account.addresses.update', $saved), array_merge($data, [
            'city' => 'Ankara', 'district' => 'Bornova',
        ]))->assertSessionHasErrors('district');
        $this->assertSame('Bornova', $saved->fresh()->district);
    }
}
