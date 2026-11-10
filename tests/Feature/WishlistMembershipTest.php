<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $this->seed(PolarisDemoSeeder::class);

        return Product::query()->where('sku', 'DEMO-SURF-125')->firstOrFail();
    }

    public function test_guest_favorite_intent_survives_login_and_saves_to_customer_account(): void
    {
        $product = $this->product();
        $user = User::factory()->create(['password' => 'Pass12345!']);

        $this->get(route('account.login', ['next' => 'cart', 'favorite' => $product->id]))
            ->assertOk()
            ->assertSee('name="favorite" value="'.$product->id.'"', false);

        $this->post(route('account.login.submit'), [
            'email' => $user->email,
            'password' => 'Pass12345!',
            'next' => 'cart',
            'favorite' => $product->id,
        ])->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', 'Ürün favorilere eklendi.');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->get(route('wishlist.index'))->assertOk()->assertSee($product->name);
    }

    public function test_guest_favorite_intent_survives_registration(): void
    {
        $product = $this->product();

        $this->get(route('account.register', ['next' => 'cart', 'favorite' => $product->id]))
            ->assertOk()
            ->assertSee('name="favorite" value="'.$product->id.'"', false);

        $this->post(route('account.register.submit'), [
            'name' => 'Test Müşterisi',
            'email' => 'guest-newtic@example.com',
            'password' => 'Pass12345!',
            'password_confirmation' => 'Pass12345!',
            'next' => 'cart',
            'favorite' => $product->id,
        ])->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', 'Ürün favorilere eklendi.');

        $user = User::query()->where('email', 'guest-newtic@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_wishlist_and_post_toggle_are_members_only_but_cart_is_public(): void
    {
        $product = $this->product();

        $this->get(route('cart.index'))->assertOk();
        $this->get(route('wishlist.index'))->assertRedirect(route('account.login'));
        $this->post(route('wishlist.toggle', $product))->assertRedirect(route('account.login'));
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_pending_favorite_does_not_save_unavailable_product(): void
    {
        $product = $this->product();
        $product->update(['is_active' => false]);
        $user = User::factory()->create(['password' => 'Pass12345!']);

        $this->post(route('account.login.submit'), [
            'email' => $user->email,
            'password' => 'Pass12345!',
            'next' => 'cart',
            'favorite' => $product->id,
        ])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('wishlist_items', 0);
    }
}
