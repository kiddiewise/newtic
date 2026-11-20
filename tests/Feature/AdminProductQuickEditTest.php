<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminProductQuickEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function category(string $name = 'Surf Kurşun'): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.strtolower(\Illuminate\Support\Str::random(5)),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::create([
            'name' => 'Renkli Surf Kurşun 125 gr',
            'slug' => 'renkli-surf-125',
            'sku' => 'RS-125',
            'primary_category_id' => $category->id,
            'price' => '150.00',
            'compare_at_price' => '200.00',
            'stock_quantity' => 11,
            'tracks_stock' => true,
            'is_active' => true,
            'requires_customer_photos' => true,
            'description' => 'Kullanım açıklaması',
            'seo_title' => 'SEO değiştirilmemeli',
        ]);
    }

    private function quick(Product $product, string $field, mixed $value): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson(route('admin.products.quick-update', $product), [
            'field' => $field,
            'value' => $value,
        ]);
    }

    public function test_product_list_includes_quick_edit_trigger_and_all_required_fields(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $this->actingAs($this->admin());

        $response = $this->get(route('admin.products.index'))->assertOk()
            ->assertSee('Hızlı düzenle')
            ->assertSee('data-quick-edit-open')
            ->assertSee('data-quick-editor')
            ->assertSee('data-quick-feedback')
            ->assertSee('data-quick-edit-row="'.$product->id.'"', false)
            ->assertSee(route('admin.products.quick-update', $product))
            ->assertSee('Değişiklikler otomatik kaydedilir.');

        foreach (['name', 'primary_category_id', 'regular_price', 'sale_price', 'stock_quantity'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        $response->assertSee('class="admin-quick-edit-footer admin-quick-edit-wide"', false);
        $response->assertSee('Kategori seçin');
    }

    public function test_nonadmins_cannot_read_or_write_product_quick_edit(): void
    {
        $product = $this->product($this->category());

        $this->quick($product, 'name', 'Yetkisiz')->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->quick($product, 'name', 'Yetkisiz')->assertForbidden();
        $this->assertSame('Renkli Surf Kurşun 125 gr', $product->fresh()->name);
    }

    public function test_quick_edit_saves_name_category_and_stock_without_changing_product_links_or_other_fields(): void
    {
        $category = $this->category();
        $destination = $this->category('Aparatlı Surf Kurşun');
        $product = $this->product($category);
        $originalSlug = $product->slug;
        $this->actingAs($this->admin());

        $this->quick($product, 'name', 'Yeni Renkli Surf Kurşun')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product.name', 'Yeni Renkli Surf Kurşun');

        $this->quick($product, 'primary_category_id', $destination->id)
            ->assertOk()
            ->assertJsonPath('product.category_name', 'Aparatlı Surf Kurşun')
            ->assertJsonPath('product.primary_category_id', $destination->id);

        $this->quick($product, 'stock_quantity', 3)
            ->assertOk()->assertJsonPath('product.stock_quantity', 3);

        $product->refresh();
        $this->assertSame('Yeni Renkli Surf Kurşun', $product->name);
        $this->assertSame($destination->id, $product->primary_category_id);
        $this->assertSame(3, $product->stock_quantity);
        $this->assertSame($originalSlug, $product->slug);
        $this->assertSame('RS-125', $product->sku);
        $this->assertSame('150.00', $product->price);
        $this->assertSame('200.00', $product->compare_at_price);
        $this->assertSame('Kullanım açıklaması', $product->description);
        $this->assertSame('SEO değiştirilmemeli', $product->seo_title);
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->requires_customer_photos);
    }

    public function test_normal_and_sale_prices_update_independently_and_discount_can_be_removed(): void
    {
        $product = $this->product($this->category());
        $this->actingAs($this->admin());

        $this->quick($product, 'regular_price', '250.00')
            ->assertOk()->assertJsonPath('product.regular_price', '250.00')
            ->assertJsonPath('product.sale_price', '150.00');
        $this->assertSame('250.00', $product->fresh()->compare_at_price);
        $this->assertSame('150.00', $product->fresh()->price);

        $this->quick($product, 'sale_price', '180.50')
            ->assertOk()->assertJsonPath('product.price', '180.50')
            ->assertJsonPath('product.discount_percent', 28);
        $this->assertSame('180.50', $product->fresh()->price);
        $this->assertSame('250.00', $product->fresh()->compare_at_price);

        $this->quick($product, 'sale_price', null)
            ->assertOk()->assertJsonPath('product.sale_price', null)
            ->assertJsonPath('product.price', '250.00')
            ->assertJsonPath('product.discount_percent', 0);
        $this->assertNull($product->fresh()->compare_at_price);
        $this->assertSame('250.00', $product->fresh()->price);

        $this->quick($product, 'regular_price', '120.00')
            ->assertOk()->assertJsonPath('product.price', '120.00');
        $this->assertSame('120.00', $product->fresh()->price);
        $this->assertNull($product->fresh()->compare_at_price);
    }

    public function test_invalid_discount_and_invalid_regular_price_leave_existing_values_untouched(): void
    {
        $product = $this->product($this->category());
        $this->actingAs($this->admin());

        $this->quick($product, 'sale_price', '200.00')
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'regular_price', '149.00')
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'sale_price', '0')
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'regular_price', '-5')
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'regular_price', '150.555')
            ->assertStatus(422)->assertJsonValidationErrors('value');

        $this->assertSame('150.00', $product->fresh()->price);
        $this->assertSame('200.00', $product->fresh()->compare_at_price);
    }

    public function test_invalid_name_category_stock_or_unknown_field_will_not_change_product(): void
    {
        $product = $this->product($this->category());
        $this->actingAs($this->admin());

        $this->quick($product, 'name', '')
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'primary_category_id', 9999999)
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'stock_quantity', -1)
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'stock_quantity', 1.5)
            ->assertStatus(422)->assertJsonValidationErrors('value');
        $this->quick($product, 'is_active', false)
            ->assertStatus(422)->assertJsonValidationErrors('field');
        $this->quick($product, 'sale_price', 'not-a-price')
            ->assertStatus(422)->assertJsonValidationErrors('value');

        $this->assertSame('Renkli Surf Kurşun 125 gr', $product->fresh()->name);
        $this->assertSame(11, $product->fresh()->stock_quantity);
        $this->assertSame('150.00', $product->fresh()->price);
    }

    public function test_stock_quantity_is_saved_without_turning_on_stock_tracking(): void
    {
        $product = $this->product($this->category());
        $product->update(['tracks_stock' => false, 'stock_quantity' => 999]);
        $this->actingAs($this->admin());

        $this->quick($product, 'stock_quantity', 14)
            ->assertOk()
            ->assertJsonPath('product.stock_quantity', 14)
            ->assertJsonPath('product.tracks_stock', false);
        $product->refresh();
        $this->assertFalse($product->tracks_stock);
        $this->assertSame(14, $product->stock_quantity);
    }

    public function test_javascript_uses_serialized_autosave_and_mobile_sheet_css_is_bundled(): void
    {
        $script = file_get_contents(resource_path('js/admin-product-quick-edit.js'));
        $style = file_get_contents(resource_path('css/admin-product-quick-edit.css'));
        $this->assertStringContainsString("const pending = new Map()", $script);
        $this->assertStringContainsString("method: 'PATCH'", $script);
        $this->assertStringContainsString("X-CSRF-TOKEN", $script);
        $this->assertStringContainsString("setTimeout(flush", $script);
        $this->assertStringContainsString("value === saved[field]", $script);
        $this->assertStringContainsString("admin-quick-edit-dialog", $style);
        $this->assertStringContainsString("inset:auto 0 0", $style);
        $this->assertStringContainsString("import './admin-product-quick-edit.js'", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString("@import './admin-product-quick-edit.css'", file_get_contents(resource_path('css/app.css')));
    }
}
