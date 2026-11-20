<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminCategoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'parent_id' => $parent?->id,
            'is_active' => true,
        ]);
    }

    private function product(Category $category, string $sku): Product
    {
        return Product::create([
            'name' => 'Ürün '.$sku,
            'sku' => $sku,
            'slug' => strtolower($sku).'-'.Str::lower(Str::random(5)),
            'primary_category_id' => $category->id,
            'price' => '250.00',
            'stock_quantity' => 7,
            'is_active' => true,
            'requires_customer_photos' => true,
        ]);
    }

    private function move(Category $source, Category $target, string $operation = 'move'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.categories.transfer', $source), [
            'target_category_id' => $target->id,
            'operation' => $operation,
        ]);
    }

    public function test_only_admin_can_transfer_categories(): void
    {
        $source = $this->category('Kaynak');
        $target = $this->category('Hedef');
        $product = $this->product($source, 'AUTH-100');
        $this->move($source, $target)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->move($source, $target)->assertForbidden();
        $this->assertSame($source->id, $product->fresh()->primary_category_id);
    }

    public function test_category_list_opens_one_popup_with_source_and_target_actions(): void
    {
        $source = $this->category('Karikatür Biblolar');
        $target = $this->category('Hediyelik');
        $product = $this->product($source, 'BIBLO-001');
        $this->product($target, 'HEDIYE-002');
        $this->actingAs($this->admin());
        $html = $this->get(route('admin.categories.index'))->assertOk()
            ->assertSee('data-category-delete-open')
            ->assertSee('data-category-transfer-dialog')
            ->assertSee('Ürünleri Taşı')
            ->assertSee('Ürünleri Taşı ve Sil')
            ->assertSee('data-product-count="1"', false)
            ->assertSee(route('admin.categories.transfer', $source))
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-category-transfer-dialog'));
        $this->assertStringContainsString($target->name, $html);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'primary_category_id' => $source->id]);
    }

    public function test_move_only_reassigns_primary_and_secondary_memberships_without_deleting_source(): void
    {
        $source = $this->category('Kaynak');
        $target = $this->category('Hedef');
        $other = $this->category('Başka');
        $primary = $this->product($source, 'A-100');
        $secondary = $this->product($other, 'B-100');
        $secondary->categories()->attach($source->id);
        $primary->categories()->attach($source->id);
        $primary->categories()->attach($target->id); // already target membership, no duplicates
        $this->actingAs($this->admin());

        $this->get(route('admin.categories.index'))
            ->assertSee('data-product-count="2"', false);
        $this->move($source, $target)->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, '2 ürün')
                && str_contains($message, 'korundu'));

        $this->assertDatabaseHas('categories', ['id' => $source->id]);
        $this->assertSame($target->id, $primary->fresh()->primary_category_id);
        $this->assertSame($other->id, $secondary->fresh()->primary_category_id);
        $this->assertSame(0, DB::table('category_product')->where('category_id', $source->id)->count());
        $this->assertSame(2, DB::table('category_product')->where('category_id', $target->id)->count());
        $this->assertSame('250.00', $primary->fresh()->price);
        $this->assertSame(7, $primary->fresh()->stock_quantity);
        $this->assertTrue($primary->fresh()->requires_customer_photos);
    }

    public function test_move_and_delete_removes_source_and_its_own_uploaded_images_only(): void
    {
        Storage::fake('public');
        $source = $this->category('Silinecek');
        $target = $this->category('Kalacak');
        $product = $this->product($source, 'MOVE-DELETE');
        $secondary = $this->product($target, 'SECONDARY');
        $secondary->categories()->attach($source->id);
        $source->update(['image_path' => 'storage/categories/source.webp']);
        $source->banners()->create(['desktop_image_path' => 'storage/category-banners/source.webp']);
        Storage::disk('public')->put('categories/source.webp', 'fake');
        Storage::disk('public')->put('category-banners/source.webp', 'fake');
        Storage::disk('public')->put('products/product.webp', 'keep this media');
        $product->update(['image_path' => 'storage/products/product.webp']);
        $this->actingAs($this->admin());

        $this->move($source, $target, 'move_and_delete')
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $source->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'primary_category_id' => $target->id]);
        $this->assertSame(2, Product::count());
        $this->assertDatabaseHas('products', ['id' => $secondary->id, 'primary_category_id' => $target->id]);
        $this->assertSame(0, DB::table('category_product')->where('category_id', $source->id)->count());
        $this->assertSame(1, DB::table('category_product')->where('category_id', $target->id)->count());
        Storage::disk('public')->assertMissing('categories/source.webp');
        Storage::disk('public')->assertMissing('category-banners/source.webp');
        Storage::disk('public')->assertExists('products/product.webp');
    }

    public function test_children_block_delete_but_not_move_only(): void
    {
        $source = $this->category('Üst');
        $child = $this->category('Alt', $source);
        $target = $this->category('Hedef');
        $product = $this->product($source, 'PARENT-PROD');
        $this->actingAs($this->admin());

        $this->move($source, $target, 'move_and_delete')->assertSessionHasErrors('delete');
        $this->assertSame($source->id, $product->fresh()->primary_category_id);
        $this->assertSame($source->id, $child->fresh()->parent_id);
        $this->move($source, $target)->assertSessionHasNoErrors();
        $this->assertSame($target->id, $product->fresh()->primary_category_id);
        $this->assertDatabaseHas('categories', ['id' => $source->id]);
        $this->assertSame($source->id, $child->fresh()->parent_id);
    }

    public function test_empty_source_cannot_be_transferred_and_normal_delete_remains_available(): void
    {
        $source = $this->category('Boş');
        $target = $this->category('Hedef');
        $this->actingAs($this->admin());
        $this->move($source, $target)->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('categories', ['id' => $source->id]);
        $this->delete(route('admin.categories.destroy', $source))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $source->id]);
    }

    public function test_same_target_and_invalid_target_or_operation_cannot_change_products(): void
    {
        $source = $this->category('A');
        $target = $this->category('B');
        $product = $this->product($source, 'INVALID-MOVE');
        $this->actingAs($this->admin());

        $this->move($source, $source)->assertSessionHasErrors('target_category_id');
        $this->post(route('admin.categories.transfer', $source), [
            'target_category_id' => 999999,
            'operation' => 'move_and_delete',
        ])->assertSessionHasErrors('target_category_id');
        $this->post(route('admin.categories.transfer', $source), [
            'target_category_id' => $target->id,
            'operation' => 'delete_all_products',
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseHas('categories', ['id' => $source->id]);
        $this->assertSame($source->id, $product->fresh()->primary_category_id);
    }

    public function test_transfer_into_child_is_allowed_only_if_source_is_kept(): void
    {
        $parent = $this->category('Ana');
        $child = $this->category('Alt', $parent);
        $product = $this->product($parent, 'CHILD-MOVE');
        $this->actingAs($this->admin());
        $this->move($parent, $child, 'move_and_delete')->assertSessionHasErrors('delete');
        $this->move($parent, $child)->assertSessionHasNoErrors();
        $this->assertSame($child->id, $product->fresh()->primary_category_id);
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }
}
