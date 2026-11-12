<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\AddressValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurkishPhoneCheckoutExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_phone_input_shows_fixed_turkey_code_and_mobile_keyboard(): void
    {
        $category = Category::create([
            'name' => 'Surf',
            'slug' => 'surf',
            'is_active' => true,
        ]);
        $product = Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Polaris Surf 75',
            'sku' => 'PHONE-75',
            'slug' => 'phone-75',
            'price' => '49.90',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $this->post(route('cart.add'), ['items' => [$product->id => 1]])->assertRedirect();

        $html = $this->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-tr-phone-control', $html);
        $this->assertStringContainsString('🇹🇷', $html);
        $this->assertStringContainsString('class="tr-phone-dial">+90', $html);
        $this->assertStringContainsString('data-tr-mobile-phone', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('autocomplete="tel"', $html);
        $this->assertStringContainsString('placeholder="555 555 55 55"', $html);
        $this->assertStringContainsString('id="checkout-phone-hint"', $html);
        $this->assertStringContainsString('data-checkout-state="locked"', $html);
    }

    public function test_phone_is_saved_in_canonical_form_and_formatted_for_display(): void
    {
        foreach ([
            '555 555 55 55',
            '0555 555 55 55',
            '+90 555 555 55 55',
            '905555555555',
            '0090 555 555 55 55',
        ] as $input) {
            $this->assertSame('05555555555', AddressValidation::normalizePhone($input), $input);
            $this->assertSame('555 555 55 55', AddressValidation::formatForEntry($input), $input);
        }

        $this->assertSame('', AddressValidation::formatForEntry(null));
        $this->assertSame('12345', AddressValidation::formatForEntry('12345'));
    }

    public function test_address_book_uses_the_same_turkish_phone_field(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $response = $this->get(route('account.addresses.create'))->assertOk();
        $response->assertSee('data-tr-mobile-phone')
            ->assertSee('555 555 55 55')
            ->assertSee('+90');

        $this->post(route('account.addresses.store'), [
            'address_label' => 'Ev',
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '555 555 55 55',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi, Ana Cadde, No 20',
            'invoice_type' => 'individual',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_addresses', [
            'phone' => '05555555555',
        ]);

        $address = \App\Models\CustomerAddress::firstOrFail();
        $this->get(route('account.addresses.edit', $address))->assertOk()
            ->assertSee('value="555 555 55 55"', false);
    }

    public function test_invalid_numbers_do_not_become_valid_from_the_prefix(): void
    {
        foreach (['', '12345', '232 123 45 67', '555 555 55'] as $number) {
            $normalized = AddressValidation::normalizePhone($number);
            $this->assertSame(0, preg_match('/^0?5[0-9]{9}$/', $normalized));
        }
    }
}
