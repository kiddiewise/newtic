<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AnalyticsCenterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $session, string $event = 'page_view', array $more = []): array
    {
        return array_merge([
            'consent' => true,
            'session' => $session,
            'event' => $event,
            'path' => '/kategori/renkli-surf-kursun',
        ], $more);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Renkli Surf Kurşun',
            'slug' => 'renkli-surf-kursun',
            'is_active' => true,
        ]);

        return Product::query()->create([
            'name' => '125 Gr. Polaris Renkli Surf Kurşun',
            'slug' => 'renkli-surf-125',
            'sku' => 'ANALYTICS125',
            'primary_category_id' => $category->id,
            'price' => '94.90',
            'stock_quantity' => 25,
            'is_active' => true,
        ]);
    }

    private function order(bool $test, string $status = 'preparing'): Order
    {
        return Order::query()->create([
            'order_number' => 'PK-ANALYTICS-'.Str::upper(Str::random(8)),
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Sample Customer',
            'customer_email' => 'customer@example.test',
            'phone' => '5555555555',
            'city' => 'İzmir',
            'district' => 'Buca',
            'street_address' => 'Test',
            'invoice_type' => 'individual',
            'payment_method' => 'bank_transfer',
            'status' => $status,
            'subtotal_cents' => 10000,
            'shipping_cents' => 0,
            'total_cents' => 10000,
            'currency' => 'TRY',
            'is_test' => $test,
            'placed_at' => now(),
        ]);
    }

    private function verify(Order $order): void
    {
        PaymentNotification::query()->create([
            'order_id' => $order->id,
            'bank_name_snapshot' => 'Test Bank',
            'iban_snapshot' => 'TR000000000000000000000000',
            'status' => 'verified',
        ]);
    }

    public function test_events_require_explicit_analytics_consent(): void
    {
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), [
            'session' => $id, 'event' => 'page_view', 'path' => '/',
        ])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson(route('analytics.event'), $this->payload($id, more:['consent' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertDatabaseCount('analytics_sessions', 0);
    }

    public function test_consent_records_anonymous_events_and_live_session_without_ip_or_identity(): void
    {
        $id = (string) Str::uuid();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.17']);
        $this->postJson(route('analytics.event'), $this->payload($id))
            ->assertStatus(202)->assertJsonPath('ok', true);
        $this->postJson(route('analytics.event'), $this->payload($id, 'heartbeat'))
            ->assertStatus(202);

        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseCount('analytics_sessions', 1);
        $key = hash_hmac('sha256', $id, (string) config('app.key'));
        $this->assertDatabaseHas('analytics_sessions', [
            'session_key' => $key, 'current_path' => '/kategori/renkli-surf-kursun',
        ]);
        $row = DB::table('analytics_sessions')->first();
        $this->assertFalse(property_exists($row, 'ip'));
        $this->assertFalse(property_exists($row, 'user_id'));
        $this->assertFalse(property_exists($row, 'customer_email'));
        $this->assertFalse(property_exists($row, 'latitude'));
        $this->assertNotSame($id, $row->session_key);
    }

    public function test_sensitive_routes_and_obvious_personal_search_terms_are_ignored(): void
    {
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($id, more:[
            'path' => '/hesabim',
        ]))->assertStatus(202);
        $this->postJson(route('analytics.event'), $this->payload($id, more:[
            'path' => '/odeme/paytr/31',
        ]))->assertStatus(202);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->postJson(route('analytics.event'), $this->payload($id, 'search', [
            'path' => '/ara', 'term' => 'personal@example.com', 'results_count' => 0,
        ]))->assertStatus(202);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'search', 'search_term' => null, 'results_count' => 0,
        ]);
        $this->postJson(route('analytics.event'), $this->payload($id, 'search', [
            'path' => '/ara', 'term' => 'Fosforlu Kurşun', 'results_count' => 4,
        ]))->assertStatus(202);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'search', 'search_term' => 'fosforlu kurşun', 'results_count' => 4,
        ]);
    }

    public function test_product_events_require_existing_product_and_unsupported_events_rejected(): void
    {
        $product = $this->product();
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($id, 'view_item', [
            'path' => '/urun/renkli-surf-125',
            'product_id' => $product->id,
        ]))->assertStatus(202);
        $this->postJson(route('analytics.event'), $this->payload($id, 'add_to_cart', [
            'path' => '/urun/renkli-surf-125',
            'product_id' => $product->id,
        ]))->assertStatus(202);
        $this->assertDatabaseCount('analytics_events', 2);
        $this->postJson(route('analytics.event'), $this->payload($id, 'purchase'))
            ->assertStatus(422)->assertJsonValidationErrors('event');
        $this->postJson(route('analytics.event'), $this->payload($id, 'view_item', [
            'product_id' => 999999,
        ]))->assertStatus(202);
        $this->assertDatabaseCount('analytics_events', 2);
    }

    public function test_withdrawing_analytics_permission_deletes_session_and_its_events(): void
    {
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($id))->assertStatus(202);
        $this->postJson(route('analytics.forget'), ['session' => $id])
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseCount('analytics_events', 0);
        $this->assertDatabaseCount('analytics_sessions', 0);
    }

    public function test_analytics_dashboard_and_live_endpoint_are_admin_only(): void
    {
        $this->get(route('admin.analytics'))->assertRedirect();
        $this->getJson(route('admin.analytics.live'))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.analytics'))->assertForbidden();
        $this->getJson(route('admin.analytics.live'))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.analytics'))->assertOk()
            ->assertSee('Pazarlama ve Analitik')
            ->assertSee('Alışveriş yolculuğu')
            ->assertSee('En çok ilgi gören ürünler')
            ->assertSee('Şu anda sitede')
            ->assertSeeInOrder(['data-live-section','data-live-count','Temel göstergeler'],false)
            ->assertDontSee('Gizlilik odaklı ölçüm')
            ->assertDontSee('TRAFFIC INTELLIGENCE');
        $this->getJson(route('admin.analytics.live'))->assertOk()
            ->assertJsonPath('online', 0)->assertJsonStructure(['online', 'visitors', 'updated_at']);
    }

    public function test_verified_real_sales_are_counted_but_unpaid_and_test_orders_are_not(): void
    {
        $this->verify($this->order(false));
        $this->verify($this->order(true));
        $this->order(false, 'pending_payment');

        $admin = $this->admin();
        $this->actingAs($admin);
        $response = $this->get(route('admin.analytics'));
        $response->assertOk()
            ->assertSee('100,00 ₺')
            ->assertSee('Banka veya PAYTR onaylı gerçek siparişler');

        $response->assertDontSee('300,00 ₺');
    }

    public function test_admin_live_page_does_not_disclose_session_key_or_ip(): void
    {
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($id, more:[
            'path' => '/urun/renkli-surf-125',
        ]))->assertStatus(202);
        $this->actingAs($this->admin());
        $json = $this->getJson(route('admin.analytics.live'))->assertOk()
            ->assertJsonPath('online', 1);
        $json->assertDontSee($id)->assertDontSee('session_key')->assertDontSee('customer_email');
        $this->assertSame('Bilinmiyor', $json->json('visitors.0.location'));
    }

    public function test_old_events_are_pruned_and_recent_events_preserved(): void
    {
        $id = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($id))->assertStatus(202);
        DB::table('analytics_events')->insert([
            'session_key' => str_repeat('a', 64),
            'event_name' => 'page_view', 'page_path' => '/',
            'device' => 'mobile', 'occurred_at' => now()->subDays(95),
        ]);
        $this->artisan('analytics:prune')->assertSuccessful();
        $this->assertDatabaseCount('analytics_events', 1);
    }

    public function test_equal_length_previous_period_is_compared_without_making_up_zero_baseline_growth(): void
    {
        $uuid = (string) Str::uuid();
        $this->postJson(route('analytics.event'), $this->payload($uuid))->assertStatus(202);

        $this->actingAs($this->admin());
        $this->get(route('admin.analytics', ['days' => 30]))->assertOk()
            ->assertViewHas('comparison', fn (array $c) =>
                $c['visitors']['previous'] === 0
                && $c['visitors']['percent'] === null
                && $c['revenueCents']['percent'] === null);

        DB::table('analytics_events')->insert([
            'session_key' => str_repeat('f', 64),
            'event_name' => 'page_view', 'page_path' => '/',
            'device' => 'desktop', 'occurred_at' => now()->subDays(35),
        ]);
        DB::table('analytics_events')->insert([
            'session_key' => str_repeat('e', 64),
            'event_name' => 'page_view', 'page_path' => '/',
            'device' => 'desktop', 'occurred_at' => now()->subDays(35),
        ]);
        $this->get(route('admin.analytics', ['days' => 30]))->assertOk()
            ->assertViewHas('comparison', fn (array $c) =>
                $c['visitors']['previous'] === 2
                && $c['visitors']['percent'] === -50.0);
    }

    public function test_repeat_buyer_segments_use_verified_registered_orders_only(): void
    {
        $returning = User::factory()->create();
        $new = User::factory()->create();

        $prior = $this->order(false);
        $prior->update(['user_id' => $returning->id, 'placed_at' => now()->subDays(65)]);
        $this->verify($prior);

        $repeat = $this->order(false);
        $repeat->update(['user_id' => $returning->id]);
        $this->verify($repeat);

        $first = $this->order(false);
        $first->update(['user_id' => $new->id]);
        $this->verify($first);

        $guest = $this->order(false);
        $this->verify($guest);

        $test = $this->order(true);
        $test->update(['user_id' => $new->id]);
        $this->verify($test);

        $this->actingAs($this->admin())->get(route('admin.analytics'))->assertOk()
            ->assertViewHas('buyers', fn (array $buyers) =>
                $buyers['all'] === 2
                && $buyers['new'] === 1
                && $buyers['returning'] === 1
                && $buyers['returning_percent'] === 50.0);
    }

    public function test_sales_opportunity_needs_enough_genuine_interest_sample(): void
    {
        $product = $this->product();
        $key = str_repeat('b', 64);
        $rows = [];
        for ($i = 0; $i < 11; $i++) {
            $rows[] = [
                'session_key' => $key,
                'event_name' => 'view_item',
                'product_id' => $product->id,
                'page_path' => '/urun/renkli-surf-125',
                'device' => 'mobile', 'occurred_at' => now(),
            ];
        }
        DB::table('analytics_events')->insert($rows);
        $this->actingAs($this->admin())->get(route('admin.analytics'))->assertOk()
            ->assertViewHas('opportunities', fn ($items) =>
                $items->count() === 1
                && $items->first()['views'] === 11
                && $items->first()['cart_adds'] === 0);
    }

    public function test_admin_only_csv_contains_aggregates_and_neutralizes_catalog_formulas(): void
    {
        $this->get(route('admin.analytics.export'))->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.analytics.export'))->assertForbidden();

        $product = $this->product();
        $product->update(['name' => '=2+2']);
        $this->postJson(route('analytics.event'), $this->payload((string) Str::uuid(), 'view_item', [
            'path' => '/urun/renkli-surf-125', 'product_id' => $product->id,
        ]))->assertStatus(202);

        $this->actingAs($this->admin());
        $response = $this->get(route('admin.analytics.export', ['days' => 7]));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('sep=;', $csv);
        $this->assertStringContainsString('NEWTIC', $csv);
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringNotContainsString('customer@example.test', $csv);
        $this->assertStringNotContainsString('session_key', $csv);
        $this->get(route('admin.analytics.export', ['days' => 365]))->assertStatus(422);
    }

}
