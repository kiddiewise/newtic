<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminCatalogDeleteAndInstallDomainTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function category(string $name = 'Dekorasyon', ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Kişiye Özel Biblo',
            'slug' => 'biblo-'.Str::lower(Str::random(6)),
            'sku' => 'SD-'.Str::upper(Str::random(9)),
            'price' => '250.00',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_only_admin_can_delete_products_and_categories(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.categories.destroy', $category))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();
        $this->delete(route('admin.categories.destroy', $category))->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_delete_unsold_product_and_uploaded_gallery(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $product = $this->product($category);
        $image = 'storage/products/biblo.webp';
        Storage::disk('public')->put('products/biblo.webp', 'image bytes');
        $product->images()->create(['path' => $image, 'sort_order' => 0]);
        $product->update(['image_path' => $image]);
        $this->actingAs($this->admin());
        $this->get(route('admin.products.index'))->assertSee(route('admin.products.destroy', $product));
        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing('products/biblo.webp');
    }

    public function test_sold_product_is_kept_with_order_history_intact(): void
    {
        $product = $this->product($this->category());
        $order = Order::create([
            'order_number' => 'SD-'.Str::upper(Str::random(12)),
            'checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Deneme Müşteri',
            'customer_email' => 'customer@example.test',
            'phone' => '05321234567',
            'city' => 'İzmir',
            'district' => 'Konak',
            'street_address' => 'Test Sokak 1',
            'subtotal_cents' => 25000,
            'shipping_cents' => 0,
            'total_cents' => 25000,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price_cents' => 25000,
            'line_total_cents' => 25000,
        ]);
        $this->actingAs($this->admin());
        $this->delete(route('admin.products.destroy', $product))->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame($product->id, $order->items()->firstOrFail()->product_id);
    }

    public function test_category_delete_requires_empty_leaf_and_cleans_uploaded_images(): void
    {
        Storage::fake('public');
        $parent = $this->category('Ana');
        $child = $this->category('Alt', $parent);
        $product = $this->product($child);
        $this->actingAs($this->admin());
        $this->get(route('admin.categories.index'))->assertSee(route('admin.categories.destroy', $parent));
        $this->delete(route('admin.categories.destroy', $parent))->assertSessionHasErrors('delete');
        $this->delete(route('admin.categories.destroy', $child))->assertSessionHasErrors('delete');
        $product->update(['primary_category_id' => $parent->id]);
        $this->delete(route('admin.categories.destroy', $parent))->assertSessionHasErrors('delete');
        $image = 'storage/categories/test.webp';
        Storage::disk('public')->put('categories/test.webp', 'image bytes');
        $child->update(['image_path' => $image]);
        $child->banners()->create(['desktop_image_path' => 'storage/category-banners/test.webp']);
        Storage::disk('public')->put('category-banners/test.webp', 'image bytes');
        $this->delete(route('admin.categories.destroy', $child))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
        Storage::disk('public')->assertMissing('categories/test.webp');
        Storage::disk('public')->assertMissing('category-banners/test.webp');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_payment_callback_and_shipping_cron_are_installation_specific(): void
    {
        $this->actingAs($this->admin());
        config()->set('app.url', 'https://sinaydesign.com');
        // The canonical installation URL must be used even behind a reverse proxy.
        $html = $this->get('https://sinaydesign.com/admin/odeme-ayarlari/paytr')->assertOk()->getContent();
        $this->assertStringContainsString('https://sinaydesign.com/odeme/paytr/bildirim', $html);
        $this->assertStringNotContainsString('powderblue-dinosaur-538060', $html);
        $this->assertStringNotContainsString('Ödeme bağlantısı için kontrol listesi', $html);
        $this->assertStringNotContainsString('Güvenlik:', $html);
        $shipping = $this->get('https://sinaydesign.com/admin/kargo-operasyonlari')->assertOk()->getContent();
        $this->assertStringContainsString('scripts/cron-shipping-sync.sh', $shipping);
        $this->assertStringNotContainsString('powderblue-dinosaur-538060', $shipping);
    }

    public function test_unwanted_admin_help_texts_are_removed(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.product-imports.index'))->assertDontSee("WooCommerce'in kendi CSV formatı desteklenir");
        $this->get(route('admin.bank-accounts.index'))->assertSee('256 × 192 px');
        $this->get(route('admin.shipping-integrations.index'))->assertDontSee('API anahtarını WhatsApp');
        $this->get(route('admin.login'))->assertRedirect();
    }
}
