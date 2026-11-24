<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminMobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['is_admin'=>true])->save();
        return $user;
    }

    private function order(string $status='pending_payment'): Order
    {
        return Order::create([
            'order_number'=>'PK-MOB-'.Str::upper(Str::random(8)), 'checkout_token'=>(string)Str::uuid(),
            'customer_name'=>'Test Müşteri','customer_email'=>'test@example.com','phone'=>'05321234567',
            'address_label'=>'Ev','city'=>'İzmir','district'=>'Konak','street_address'=>'Örnek Sokak 3',
            'invoice_type'=>'individual','payment_method'=>'bank_transfer','status'=>$status,
            'subtotal_cents'=>10000,'shipping_cents'=>0,'total_cents'=>10000,'currency'=>'TRY',
            'is_test'=>true,'placed_at'=>now(),
        ]);
    }

    public function test_dashboard_actions_are_first_and_mobile_menu_links_are_real(): void
    {
        $response=$this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-admin-mobile-nav',false)->assertSee('data-admin-more-dialog',false)
            ->assertSee('admin-dashboard-actions-first',false);
        $html=$response->getContent();
        $this->assertLessThan(strpos($html,'class="admin-kpi-grid"'),strpos($html,'<h2>Hızlı işlemler</h2>'));
        $this->assertSame(1,substr_count($html,'<h2>Hızlı işlemler</h2>'));
        $nav=substr($html,strpos($html,'<nav class="admin-mobile-nav"'));
        foreach(['admin.orders.index','admin.products.index','admin.analytics','admin.categories.index',
            'admin.bank-accounts.index','admin.blog.posts.index','admin.announcements.index','admin.menus.mega-menu.edit','admin.index'] as $name) {
            $this->assertStringContainsString('href="'.route($name).'"',$nav);
        }
    }

    public function test_badge_is_private_and_clears_per_admin_on_unfiltered_inbox(): void
    {
        $first=$this->admin();$second=$this->admin();
        $this->order();$this->order('preparing');$this->order('completed');$this->order('cancelled');
        $this->getJson(route('admin.orders.badge'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('admin.orders.badge'))->assertForbidden();
        $this->actingAs($first);
        $response=$this->getJson(route('admin.orders.badge'))->assertOk()->assertJsonPath('count',2);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->get(route('admin.orders.index',['status'=>'preparing']))->assertOk();
        $this->getJson(route('admin.orders.badge'))->assertJsonPath('count',2);
        $this->get(route('admin.orders.index'))->assertOk();
        $this->getJson(route('admin.orders.badge'))->assertJsonPath('count',0);
        $this->order();
        $this->getJson(route('admin.orders.badge'))->assertJsonPath('count',1);
        $this->actingAs($second)->getJson(route('admin.orders.badge'))->assertJsonPath('count',3);
    }
}
