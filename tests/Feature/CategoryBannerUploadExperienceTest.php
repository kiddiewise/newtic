<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CategoryBannerUploadExperienceTest extends TestCase
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
        return Category::query()->create([
            'name' => 'Banner Test',
            'slug' => 'banner-test-'.random_int(1000, 9999),
            'is_active' => true,
        ]);
    }

    public function test_ajax_upload_of_both_images_returns_json_and_preserves_links(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('admin.categories.banners.store', $category), [
            'desktop_image' => UploadedFile::fake()->image('desktop.webp', 1470, 600),
            'mobile_image' => UploadedFile::fake()->image('mobile.webp', 1280, 720),
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Kategori bannerı eklendi.')
            ->assertJsonPath('redirect', route('admin.categories.edit', $category).'#category-banners');

        $banner = CategoryBanner::query()->where('category_id', $category->id)->firstOrFail();
        $this->assertNotNull($banner->mobile_image_path);
        Storage::disk('public')->assertExists(substr($banner->desktop_image_path, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($banner->mobile_image_path, strlen('storage/')));

        $this->get(route('admin.categories.edit', $category))->assertOk()
            ->assertSee('data-category-banner-upload')
            ->assertSee('data-banner-upload-message')
            ->assertSee('id="category-banners"', false);
    }

    public function test_ajax_banner_update_returns_success_json_and_old_image_removed(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());
        $banner = $category->banners()->create([
            'desktop_image_path' => 'storage/category-banners/old.webp',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('category-banners/old.webp', 'old');
        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->patch(route('admin.categories.banners.update', [$category, $banner]), [
            'desktop_image' => UploadedFile::fake()->image('new.webp', 1470, 600),
            'mobile_image' => UploadedFile::fake()->image('mobile.webp', 1280, 720),
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Kategori bannerı güncellendi.');

        $banner->refresh();
        Storage::disk('public')->assertMissing('category-banners/old.webp');
        Storage::disk('public')->assertExists(substr($banner->desktop_image_path, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($banner->mobile_image_path, strlen('storage/')));
    }

    public function test_ajax_bad_image_returns_validation_error_without_creating_banner(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());

        $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.categories.banners.store', $category), [
                'desktop_image' => UploadedFile::fake()->create('unsafe.txt', 1, 'text/plain'),
                'sort_order' => 1,
            ])->assertStatus(422)->assertJsonValidationErrors('desktop_image');
        $this->assertDatabaseCount('category_banners', 0);
    }

    public function test_original_non_javascript_form_keeps_redirect_behavior(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $this->actingAs($this->admin());
        $this->post(route('admin.categories.banners.store', $category), [
            'desktop_image' => UploadedFile::fake()->image('desktop.png', 1200, 480),
            'sort_order' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('category_banners', 1);
    }
}
