<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CategoryHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NestedCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name, '-', 'tr'),
            'parent_id' => $parent?->id,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function product(Category $category, string $sku = 'K125'): Product
    {
        return Product::create([
            'primary_category_id' => $category->id,
            'name' => 'Renkli Surf Kurşun 125 gr',
            'sku' => $sku,
            'slug' => 'surf-'.$sku,
            'price' => '79.90',
            'stock_quantity' => 20,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_deep_nested_categories_and_existing_parent_stays_untouched(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $this->actingAs($this->admin());

        $this->get(route('admin.categories.create', ['parent_id' => $root->id]))
            ->assertOk()->assertSee('Bağlı olduğu ana kategori');

        $this->post(route('admin.categories.store'), [
            'name' => 'Fırdöndülü Renkli Surf Kurşun',
            'parent_id' => $root->id, 'sort_order' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $child = Category::query()->where('parent_id', $root->id)->firstOrFail();
        $this->post(route('admin.categories.store'), [
            'name' => 'Fırdöndülü Aparatlı Surf Kurşun',
            'parent_id' => $child->id, 'sort_order' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $leaf = Category::query()->where('parent_id', $child->id)->firstOrFail();
        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame([$root->id, $child->id, $leaf->id], CategoryHierarchy::descendantIds($root));

        $this->get(route('admin.categories.index'))->assertOk()
            ->assertSee('Fırdöndülü Aparatlı Surf Kurşun')
            ->assertSee('+ Alt kategori');
    }

    public function test_parent_page_shows_subcategories_descendant_products_and_breadcrumbs(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Klasik Renkli Surf Kurşun', $root);
        $leaf = $this->category('Aparatlı Renkli Surf Kurşun', $child);
        $product = $this->product($leaf);

        $this->get(route('category.show', $root->slug))->assertOk()
            ->assertSee($child->name)->assertSee($product->name)
            ->assertSee(route('category.show', $child->slug))
            ->assertSee('Ürünler')
            ->assertDontSee('Tüm alt kategori ürünleri')
            ->assertDontSee('KATEGORİ SEÇİMİ');

        $this->get(route('category.show', $child->slug))->assertOk()
            ->assertSee($leaf->name)->assertSee($product->name);

        $this->get(route('category.show', $leaf->slug))->assertOk()
            ->assertSee($product->name)
            ->assertSee(route('category.show', $root->slug))
            ->assertSee(route('category.show', $child->slug));

        $this->get(route('product.show', $product->slug))->assertOk()
            ->assertSee($product->name);
    }

    public function test_category_has_only_banner_title_optional_description_and_products_without_breadcrumbs(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);
        $product = $this->product($child);
        $child->update(['description' => 'Fırdöndülü surf kurşunu seçenekleri.']);

        $html = $this->get(route('category.show', $child->slug))
            ->assertOk()
            ->assertSee('Fırdöndülü surf kurşunu seçenekleri.')
            ->assertSee($product->name)
            ->assertDontSee('POLARIS KURŞUN / KATEGORİLER')
            ->assertDontSee('KATEGORİ SEÇİMİ')
            ->assertDontSee('ÜRÜNLERİ KEŞFET')
            ->assertDontSee('Çeşitleri ve gramaj seçeneklerini keşfedin.')
            ->assertDontSee('category-breadcrumbs')
            ->getContent();

        $main = strpos($html, '<main class="container inner-page category-landing"');
        $title = strpos($html, '<h1>', $main);
        $description = strpos($html, 'class="category-description"', $main);
        $products = strpos($html, 'id="category-products-heading"', $main);
        $this->assertTrue($main < $title && $title < $description && $description < $products);

        $rootHtml = $this->get(route('category.show', $root->slug))->assertOk()
            ->assertSee($child->name)
            ->assertDontSee('category-description')
            ->assertDontSee('category-breadcrumbs')
            ->getContent();
        $this->assertTrue(
            strpos($rootHtml, 'class="category-page-header"') <
            strpos($rootHtml, 'class="category-children-grid"')
        );
    }

    public function test_mobile_navigation_contains_expandable_main_and_subcategories(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);
        $leaf = $this->category('Fırdöndülü Renkli Aparatlı', $child);
        $response = $this->get('/')->assertOk();

        $response->assertSee('mobile-menu-group')
            ->assertSee($root->name)
            ->assertSee($child->name)
            ->assertSee($leaf->name)
            ->assertSee('Tüm Renkli Surf Kurşun ürünleri');
    }

    public function test_prevents_assigning_parent_to_self_or_any_descendant(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Aparatlı Surf Kurşun', $root);
        $leaf = $this->category('Fırdöndülü Aparatlı', $child);
        $this->actingAs($this->admin());

        foreach ([$root->id, $child->id, $leaf->id] as $parentId) {
            $this->patch(route('admin.categories.update', $root), [
                'name' => $root->name, 'parent_id' => $parentId,
                'sort_order' => 0, 'is_active' => 1,
            ])->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame($child->id, $leaf->fresh()->parent_id);
    }

    public function test_existing_products_and_category_slugs_survive_reparenting(): void
    {
        $originalRoot = $this->category('Renkli Surf Kurşun');
        $newRoot = $this->category('Fosforlu Surf Kurşun');
        $child = $this->category('Aparatlı', $originalRoot);
        $product = $this->product($child);
        $originalSlug = $child->slug;

        $this->actingAs($this->admin());
        $this->patch(route('admin.categories.update', $child), [
            'name' => 'Aparatlı Fosforlu', 'parent_id' => $newRoot->id,
            'sort_order' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($originalSlug, $child->fresh()->slug);
        $this->assertSame($child->id, $product->fresh()->primary_category_id);
        $this->get(route('category.show', $originalRoot->slug))->assertOk()
            ->assertDontSee($product->name);
        $this->get(route('category.show', $newRoot->slug))->assertOk()
            ->assertSee($product->name);
    }

    public function test_inactive_parent_hides_descendant_pages_even_if_child_is_active(): void
    {
        $root = $this->category('Renkli Surf Kurşun', null, false);
        $child = $this->category('Klasik Surf Kurşun', $root);
        $product = $this->product($child);

        $this->get(route('category.show', $root->slug))->assertNotFound();
        $this->get(route('category.show', $child->slug))->assertNotFound();
        $this->get(route('product.show', $product->slug))->assertNotFound();
    }

    public function test_admin_product_category_selector_includes_nested_labels(): void
    {
        $root = $this->category('Renkli Surf Kurşun');
        $child = $this->category('Fırdöndülü Renkli Surf Kurşun', $root);

        $this->actingAs($this->admin());
        $this->get(route('admin.products.create'))->assertOk()
            ->assertSee('Kategori veya alt kategori seçin')
            ->assertSee('— Fırdöndülü Renkli Surf Kurşun');

        $this->post(route('admin.products.store'), [
            'name' => 'Yeni Surf Kurşun', 'sku' => 'NEW-SURF',
            'primary_category_id' => $child->id, 'regular_price' => '129.50',
            'stock_quantity' => 5, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($child->id, Product::query()->where('sku', 'NEW-SURF')->firstOrFail()->primary_category_id);
    }

    public function test_admin_can_upload_mobile_and_desktop_banner_that_appears_only_on_its_category(): void
    {
        Storage::fake('public');
        $first = $this->category('Renkli Surf Kurşun');
        $second = $this->category('Fosforlu Surf Kurşun');
        $this->actingAs($this->admin());

        $this->post(route('admin.categories.banners.store', $first), [
            'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1200, 480),
            'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 750, 420),
            'alt_text' => 'Renkli Surf Kampanyası', 'link_url' => '/kategori/'.$first->slug,
            'sort_order' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $banner = $first->banners()->firstOrFail();
        Storage::disk('public')->assertExists(substr($banner->desktop_image_path, 8));
        Storage::disk('public')->assertExists(substr($banner->mobile_image_path, 8));
        $this->get(route('category.show', $first->slug))->assertOk()
            ->assertSee('data-category-carousel')
            ->assertSee('media="(max-width: 760px)"', false)
            ->assertSee('Renkli Surf Kampanyası');
        $html = $this->get(route('category.show', $second->slug))->getContent();
        $this->assertStringNotContainsString('Renkli Surf Kampanyası', $html);
    }

    public function test_banner_schedule_active_toggle_and_ownership_are_enforced(): void
    {
        Storage::fake('public');
        $first = $this->category('Renkli Surf Kurşun');
        $second = $this->category('Fosforlu Surf Kurşun');
        $this->actingAs($this->admin());

        $this->post(route('admin.categories.banners.store', $first), [
            'desktop_image' => UploadedFile::fake()->image('desktop.png'),
            'sort_order' => 0, 'is_active' => 1,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $banner = $first->banners()->firstOrFail();
        $this->get(route('category.show', $first->slug))->assertDontSee('data-category-carousel');

        $this->patch(route('admin.categories.banners.update', [$first, $banner]), [
            'sort_order' => 0, 'is_active' => 1,
            'starts_at' => null,
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->get(route('category.show', $first->slug))->assertSee('data-category-carousel');

        $this->delete(route('admin.categories.banners.destroy', [$second, $banner]))->assertNotFound();
        $this->get(route('category.show', $first->slug))->assertSee('data-category-carousel');

        $this->delete(route('admin.categories.banners.destroy', [$first, $banner]))->assertRedirect();
        $this->assertDatabaseCount('category_banners', 0);
    }

    public function test_banner_links_must_be_internal_and_non_admins_cannot_upload(): void
    {
        Storage::fake('public');
        $category = $this->category('Renkli Surf Kurşun');
        $data = [
            'desktop_image' => UploadedFile::fake()->image('desktop.jpg'),
            'sort_order' => 1, 'is_active' => 1,
        ];

        $this->post(route('admin.categories.banners.store', $category), $data)
            ->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.categories.banners.store', $category), $data)
            ->assertForbidden();

        $this->actingAs($this->admin());
        foreach (['https://example.com', '//example.com', 'javascript:alert(1)'] as $invalid) {
            $this->post(route('admin.categories.banners.store', $category), array_merge($data, [
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg'),
                'link_url' => $invalid,
            ]))->assertSessionHasErrors('link_url');
        }
        $this->assertDatabaseCount('category_banners', 0);
    }
}
