<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBadge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBadgesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
    }

    private function fields(Category $category, array $extra = []): array
    {
        return array_merge([
            'name' => 'Rozetli Kurşun 100 gr',
            'sku' => 'ROZET-100',
            'primary_category_id' => $category->id,
            'regular_price' => '94.90',
            'stock_quantity' => 10,
            'is_active' => 1,
        ], $extra);
    }

    public function test_badge_management_is_admin_only(): void
    {
        $this->get(route('admin.product-badges.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.product-badges.index'))->assertForbidden();

        $this->post(route('admin.product-badges.store'), [
            'name' => 'Yetkisiz', 'color' => 'mint', 'sort_order' => 1,
        ])->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.product-badges.index'))
            ->assertOk()->assertSee('Popüler Ürün')->assertSee('Hızlı Teslimat');
    }

    public function test_badge_definitions_can_be_created_recolored_hidden_and_deleted(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.product-badges.store'), [
            'name' => 'Sezon Fırsatı', 'color' => 'lemon', 'sort_order' => 4,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $badge = ProductBadge::query()->where('name', 'Sezon Fırsatı')->firstOrFail();
        $this->assertSame('lemon', $badge->color);

        $this->patch(route('admin.product-badges.update', $badge), [
            'name' => 'Sezonun Fırsatı', 'color' => 'rose', 'sort_order' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($badge->fresh()->is_active);
        $this->assertSame('rose', $badge->fresh()->color);

        $this->delete(route('admin.product-badges.destroy', $badge))->assertRedirect();
        $this->assertDatabaseMissing('product_badges', ['id' => $badge->id]);
    }

    public function test_product_form_can_assign_multiple_badges_and_show_them_on_card_and_detail(): void
    {
        $category = $this->category();
        $popular = ProductBadge::where('name', 'Popüler Ürün')->firstOrFail();
        $fast = ProductBadge::where('name', 'Hızlı Teslimat')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->fields($category, [
            'badge_ids' => ['', (string) $popular->id, (string) $fast->id],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$popular->id, $fast->id],
            $product->badges()->pluck('product_badges.id')->all()
        );

        $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('Ürün rozetleri')->assertSee('Hızlı Teslimat');

        $card = $this->get(route('category.show', $category->slug))->assertOk()->getContent();
        $this->assertStringContainsString('product-badge-strip', $card);
        $this->assertStringContainsString('product-badge--lilac', $card);
        $this->assertStringContainsString('product-badge--mint', $card);
        $this->assertTrue(
            strpos($card, 'product-card-media') < strpos($card, 'product-badge-strip')
            && strpos($card, 'product-badge-strip') < strpos($card, 'product-card-brand')
        );

        $detail = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('product-badge-strip--detail', $detail);
        $this->assertStringContainsString('Popüler Ürün', $detail);

        $this->patch(route('admin.products.update', $product), $this->fields($category, [
            'badge_ids' => [''],
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(0, $product->fresh()->badges);
        $this->get(route('product.show', $product->slug))->assertDontSee('product-badge-strip--detail');
    }

    public function test_inactive_badges_are_hidden_but_existing_assignments_are_retained(): void
    {
        $category = $this->category();
        $product = Product::create([
            'primary_category_id' => $category->id, 'sku' => 'TEST-2',
            'name' => 'Deneme', 'slug' => 'deneme', 'price' => '99.90',
            'stock_quantity' => 3, 'is_active' => true,
        ]);
        $badge = ProductBadge::where('name', 'Popüler Ürün')->firstOrFail();
        $product->badges()->sync([$badge->id]);

        $this->get(route('product.show', $product->slug))->assertSee('Popüler Ürün');
        $badge->update(['is_active' => false]);
        $this->get(route('product.show', $product->slug))->assertDontSee('product-badge-strip--detail');
        $this->assertCount(1, $product->fresh()->badges);
    }

    public function test_badge_input_validation_rejects_unknown_ids_and_colors(): void
    {
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->post(route('admin.product-badges.store'), [
            'name' => 'Geçersiz Renk', 'color' => 'javascript:alert(1)', 'sort_order' => 3,
        ])->assertSessionHasErrors('color');

        $this->post(route('admin.products.store'), $this->fields($category, [
            'badge_ids' => ['999999'],
        ]))->assertSessionHasErrors('badge_ids.0');
        $this->assertDatabaseCount('products', 0);
    }
}
