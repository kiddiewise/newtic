<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
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
        return Category::create([
            'name' => 'Surf Kurşun',
            'slug' => 'surf-kursun',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function fields(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yeni Kurşun 125 gr',
            'sku' => 'POL-125',
            'primary_category_id' => $category->id,
            'description' => 'Deniz balıkçılığı kurşunu',
            'regular_price' => '200.00',
            'sale_price' => '150.00',
            'stock_quantity' => 12,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_only_administrators_can_manage_catalog(): void
    {
        $this->get('/admin/categories')->assertRedirect(route('admin.login'));
        $this->get('/admin/products')->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->get('/admin/categories')->assertForbidden();
        $this->get('/admin/products/create')->assertForbidden();
        $this->post('/admin/categories', ['name' => 'Yetkisiz'])->assertForbidden();

        $category = $this->category();
        $this->post('/admin/products', $this->fields($category))->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_create_category_with_safe_uploaded_photo_and_edit_it(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->post(route('admin.categories.store'), [
            'name' => 'Fosforlu Çupra Kurşun',
            'description' => 'Gece avı',
            'sort_order' => 3,
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('kategori.jpg', 240, 240),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $category = Category::firstOrFail();
        $this->assertSame('Fosforlu Çupra Kurşun', $category->name);
        $this->assertStringStartsWith('storage/categories/', $category->image_path);
        Storage::disk('public')->assertExists(substr($category->image_path, 8));

        $this->get(route('admin.categories.edit', $category))->assertOk()->assertSee('Fosforlu');
        $this->patch(route('admin.categories.update', $category), [
            'name' => 'Fosforlu Kurşun',
            'sort_order' => 4,
            'is_active' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Fosforlu Kurşun', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame(4, $category->fresh()->sort_order);
        $this->get(route('category.show', $category->slug))->assertNotFound();
    }

    public function test_admin_can_create_discounted_product_with_multiple_images_and_show_discount(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->post(route('admin.products.store'), $this->fields($category, [
            'images' => [
                UploadedFile::fake()->image('on.jpg', 320, 320),
                UploadedFile::fake()->image('arka.png', 320, 320),
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertSame('150.00', $product->price);
        $this->assertSame('200.00', $product->compare_at_price);
        $this->assertSame(25, $product->discount_percent);
        $this->assertSame(2, $product->images()->count());
        $this->assertSame($product->images()->first()->path, $product->image_path);

        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists(substr($image->path, 8));
        }
        $this->get(route('admin.products.index'))->assertOk()->assertSee('-%25')->assertSee('0 görüntülenme');
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Ürün fotoğraf galerisi');

        $this->get(route('category.show', $category->slug))->assertOk()
            ->assertSee('-%25')->assertSee('200,00')->assertSee('150,00');

        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertSee('-%25')->assertSee('data-gallery-thumb')->assertSee('görüntülenme');
    }

    public function test_home_product_card_shows_automatic_discount(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $product = Product::query()->firstOrFail();
        $product->update(['price' => '75.00', 'compare_at_price' => '100.00']);
        $this->get('/')->assertOk()->assertSee('-%25')
            ->assertSee('100,00')->assertSee('75,00');
    }

    public function test_sale_price_must_be_lower_than_regular_price_and_sku_unique(): void
    {
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->post(route('admin.products.store'), $this->fields($category, [
            'sale_price' => '250.00',
        ]))->assertSessionHasErrors('sale_price');
        $this->assertDatabaseCount('products', 0);

        $this->post(route('admin.products.store'), $this->fields($category))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.products.store'), $this->fields($category))
            ->assertSessionHasErrors('sku');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_admin_can_update_prices_and_remove_sale(): void
    {
        $category = $this->category();
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'POL-125',
            'name' => 'Yeni Kurşun 125 gr',
            'slug' => 'yeni-kursun-125-gr',
            'price' => 150,
            'compare_at_price' => 200,
            'stock_quantity' => 10,
            'is_active' => true,
            'is_demo' => false,
        ]);

        $this->actingAs($this->admin());
        $this->patch(route('admin.products.update', $product), $this->fields($category, [
            'regular_price' => '190.00', 'sale_price' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('190.00', $product->fresh()->price);
        $this->assertNull($product->fresh()->compare_at_price);
        $this->assertSame(0, $product->fresh()->discount_percent);
    }

    public function test_unsafe_product_photo_is_rejected(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->post(route('admin.products.store'), $this->fields($category, [
            'images' => [UploadedFile::fake()->create('file.svg', 10, 'image/svg+xml')],
        ]))->assertSessionHasErrors('images.0');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_photo_can_be_deleted_only_from_own_product(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->post(route('admin.products.store'), $this->fields($category, [
            'images' => [UploadedFile::fake()->image('photo.jpg')],
        ]))->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $photo = $product->images()->firstOrFail();
        $other = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'OTHER',
            'name' => 'Farklı ürün',
            'slug' => 'farkli-urun',
            'price' => '10.00',
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->delete(route('admin.products.images.destroy', [$other, $photo]))->assertNotFound();
        $this->delete(route('admin.products.images.destroy', [$product, $photo]))->assertRedirect();
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertMissing(substr($photo->path, 8));
        $this->assertNull($product->fresh()->image_path);
    }

    public function test_views_increment_once_per_session_per_day_and_do_not_count_admin_preview(): void
    {
        $category = $this->category();
        $product = Product::create([
            'primary_category_id' => $category->id,
            'sku' => 'VIEW-1',
            'name' => 'Görüntüleme testi',
            'slug' => 'goruntuleme-testi',
            'price' => '20.00',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $url = route('product.show', $product->slug);
        $this->get($url)->assertOk()->assertSee('1 görüntülenme');
        $this->get($url)->assertOk()->assertSee('1 görüntülenme');
        $this->assertSame(1, $product->fresh()->view_count);

        $this->travel(25)->hours();
        $this->get($url)->assertOk()->assertSee('2 görüntülenme');
        $this->assertSame(2, $product->fresh()->view_count);

        $this->actingAs($this->admin());
        $this->get($url)->assertOk();
        $this->assertSame(2, $product->fresh()->view_count);
    }
}
