<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductReviewsExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku = 'SURF-75'): Product
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Fırdöndülü Surf Kurşun',
            'slug' => 'firdondulu-surf-kursun',
            'is_active' => true,
        ]);

        return Product::create([
            'name' => 'Polaris Kurşun '.$sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'primary_category_id' => $category->id,
            'price' => '49.90',
            'stock_quantity' => 30,
            'is_active' => true,
        ]);
    }

    private function purchase(?User $user, Product $product, string $status = 'completed', bool $test = false): Order
    {
        $order = Order::create([
            'order_number' => 'PK-REVIEW-'.strtoupper(Str::random(10)),
            'checkout_token' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'customer_name' => $user?->name ?? 'Misafir Müşteri',
            'customer_email' => $user?->email ?? 'guest@example.org',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Atatürk Mahallesi 30 Sokak No 5',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'status' => $status,
            'subtotal_cents' => 4990,
            'shipping_cents' => 0,
            'total_cents' => 4990,
            'currency' => 'TRY',
            'is_test' => $test,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price_cents' => 4990,
            'line_total_cents' => 4990,
        ]);

        return $order;
    }

    private function submitReview(Product $product, int $rating = 5, string $body = 'Denizde kullandım, dengesi çok iyi ve işçiliğini beğendim.'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('product.reviews.store', $product), [
            'rating' => $rating, 'body' => $body,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_product_detail_shows_five_star_summary_and_review_section_without_reviews(): void
    {
        $p = $this->product();

        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('Ürün yorumları')
            ->assertSee('Müşteri değerlendirmeleri')
            ->assertSee('Henüz yayınlanmış yorum yok')
            ->assertSee('Giriş yap ve değerlendir')
            ->assertSee('1', false)
            ->assertSee('5', false);
    }

    public function test_guests_and_non_buyers_cannot_publish_ratings(): void
    {
        $p = $this->product();

        $this->submitReview($p)->assertRedirect(route('account.login'));
        $this->actingAs(User::factory()->create());
        $this->submitReview($p)->assertSessionHasErrors('review');
        $this->assertDatabaseCount('product_reviews', 0);
        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('durumunda olması gerekir');
    }

    public function test_pending_unpaid_cancelled_and_different_product_orders_cannot_be_reviewed(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $other = $this->product('SURF-100');
        foreach (['pending_payment', 'preparing', 'shipped', 'cancelled'] as $status) {
            $this->purchase($user, $p, $status);
        }
        $this->purchase($user, $other, 'completed');

        $this->actingAs($user);
        $this->submitReview($p)->assertSessionHasErrors('review');
        $this->submitReview($other)->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('pending', ProductReview::firstOrFail()->status);
    }

    public function test_registered_buyer_can_review_independent_gram_skus_once_each(): void
    {
        $user = User::factory()->create();
        $p75 = $this->product();
        $p100 = $this->product('SURF-100');
        $this->purchase($user, $p75);
        $this->purchase($user, $p100);

        $this->actingAs($user);
        $this->submitReview($p75, 5)->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->submitReview($p100, 3)->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $p75->id, 'user_id' => $user->id, 'rating' => 5, 'status' => 'pending',
        ]);
        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $p100->id, 'user_id' => $user->id, 'rating' => 3, 'status' => 'pending',
        ]);
        $this->assertSame(2, ProductReview::count());

        $this->get(route('product.show', $p75->slug))->assertOk()
            ->assertSee('Yorumunuz inceleme aşamasında')
            ->assertSee('Değerlendirmemi güncelle');
    }

    public function test_invalid_rating_and_too_short_body_are_rejected(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $this->purchase($user, $p);
        $this->actingAs($user);

        $this->submitReview($p, 6)->assertSessionHasErrors('rating');
        $this->submitReview($p, 0)->assertSessionHasErrors('rating');
        $this->submitReview($p, 3, 'Kısa')->assertSessionHasErrors('body');
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_moderation_approval_controls_public_star_average_and_never_shows_pending_reviews(): void
    {
        $p = $this->product();
        $buyer1 = User::factory()->create(['name' => 'Zeynep Balıkçı']);
        $buyer2 = User::factory()->create(['name' => 'Mert Yılmaz']);
        $this->purchase($buyer1, $p);
        $this->purchase($buyer2, $p);
        $this->actingAs($buyer1);
        $this->submitReview($p, 5, 'Denizde kullandım, çok memnun kaldım. Kalitesi gerçekten çok iyi.');
        $this->actingAs($buyer2);
        $this->submitReview($p, 3, 'İşimi gördü ama beklentim biraz daha yüksekti. Kullanılır.');

        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('Henüz yayınlanmış yorum yok');

        $admin = $this->admin();
        $this->actingAs($admin);
        $this->get(route('admin.reviews.index'))->assertOk()
            ->assertSee($p->name)
            ->assertSee('Zeynep Balıkçı')
            ->assertSee('Onayla')
            ->assertSee('Reddet');

        $approvedReview = ProductReview::query()->where('user_id', $buyer1->id)->firstOrFail();
        $this->post(route('admin.reviews.moderate', $approvedReview), [
            'decision' => 'approved',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create());
        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('1 doğrulanmış yorum')
            ->assertSee('5,0')
            ->assertSee('Denizde kullandım, çok memnun kaldım.')
            ->assertDontSee('İşimi gördü ama beklentim biraz daha yüksekti.')
            ->assertSee('Zeynep');
    }

    public function test_editing_an_approved_review_returns_it_to_moderation(): void
    {
        $buyer = User::factory()->create();
        $p = $this->product();
        $this->purchase($buyer, $p);
        $this->actingAs($buyer);
        $this->submitReview($p, 4);

        $review = ProductReview::firstOrFail();
        $this->actingAs($this->admin());
        $this->post(route('admin.reviews.moderate', $review), ['decision' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->actingAs($buyer);
        $this->submitReview($p, 2, 'İkinci kullanımımda beklediğim performansı bulamadım.')->assertSessionHasNoErrors();
        $this->assertSame(1, ProductReview::count());
        $this->assertSame('pending', $review->fresh()->status);
        $this->assertNull($review->fresh()->moderated_at);
        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('Henüz yayınlanmış yorum yok');
    }

    public function test_test_orders_can_be_dry_run_reviewed_but_never_change_public_product_rating(): void
    {
        $p = $this->product();
        $buyer = User::factory()->create();
        $this->purchase($buyer, $p, 'completed', true);
        $this->actingAs($buyer);
        $this->submitReview($p)->assertSessionHasNoErrors();

        $review = ProductReview::firstOrFail();
        $this->assertTrue($review->is_test);
        $this->actingAs($this->admin());
        $this->get(route('admin.reviews.index'))->assertOk()->assertSee('TEST YORUMU');
        $this->post(route('admin.reviews.moderate', $review), ['decision' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->get(route('product.show', $p->slug))->assertOk()
            ->assertSee('Henüz yayınlanmış yorum yok');
        $this->assertSame('approved', $review->fresh()->status);
    }

    public function test_non_admin_cannot_read_or_moderate_private_reviews(): void
    {
        $p = $this->product();
        $buyer = User::factory()->create();
        $this->purchase($buyer, $p);
        $this->actingAs($buyer);
        $this->submitReview($p);

        $review = ProductReview::firstOrFail();
        $this->get(route('admin.reviews.index'))->assertForbidden();
        $this->post(route('admin.reviews.moderate', $review), ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $review->fresh()->status);

        $this->actingAs($this->admin());
        $this->post(route('admin.reviews.moderate', $review), ['decision' => 'rejected'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $review->fresh()->status);
        $this->post(route('admin.reviews.moderate', $review), ['decision' => 'approved'])
            ->assertSessionHasErrors('review');
    }

    public function test_unauthorized_guest_order_with_matching_email_is_not_a_verified_purchase(): void
    {
        $p = $this->product();
        $user = User::factory()->create(['email' => 'guest@example.org']);
        $this->purchase(null, $p);
        $this->actingAs($user);
        $this->submitReview($p)->assertSessionHasErrors('review');
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_review_intent_login_returns_customer_to_product(): void
    {
        $p = $this->product();
        $user = User::factory()->create(['password' => 'ReviewPassword2026!']);
        $this->get(route('account.login', ['next' => 'review', 'review_product' => $p->slug]))
            ->assertOk()->assertSee('name="review_product"', false);

        $this->post(route('account.login.submit'), [
            'email' => $user->email,
            'password' => 'ReviewPassword2026!',
            'next' => 'review',
            'review_product' => $p->slug,
        ])->assertRedirect(route('product.show', $p->slug).'#urun-yorumlari');
    }
}
