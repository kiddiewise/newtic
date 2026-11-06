<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\HomeSlide;
use App\Models\User;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_anonymous_visitors_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/sections/1/edit')->assertRedirect(route('admin.login'));
    }

    public function test_logged_in_non_admin_cannot_manage_the_site(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/sections', ['type' => 'slider'])->assertForbidden();
    }

    public function test_admin_can_open_panel_and_edit_section_settings(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $admin = $this->administrator();
        $this->actingAs($admin);

        $this->get('/admin')->assertOk()->assertSee('Ana sayfa blokları');
        $section = HomeSection::query()->where('type', 'slider')->firstOrFail();
        $this->get(route('admin.sections.edit', $section))->assertOk()->assertSee('Slider görselleri');

        $this->patch(route('admin.sections.update', $section), [
            'title' => 'Yeni banner seçkisi',
            'sort_order' => 9,
            'is_active' => 1,
            'interval_ms' => 6000,
        ])->assertRedirect();

        $this->assertDatabaseHas('home_sections', [
            'id' => $section->id, 'title' => 'Yeni banner seçkisi', 'sort_order' => 9, 'is_active' => true,
        ]);
    }

    public function test_admin_controls_selected_categories_and_their_order(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->administrator());

        $section = HomeSection::query()->where('type', 'categories')->firstOrFail();
        $first = Category::query()->where('slug', 'renkli-surf-kursun')->firstOrFail();
        $second = Category::query()->where('slug', 'fosforlu-kursun')->firstOrFail();

        $this->put(route('admin.sections.selections', $section), [
            'selected' => [$first->id, $second->id],
            'position' => [$first->id => 8, $second->id => 1],
        ])->assertRedirect();

        $this->assertSame([$second->id, $first->id], $section->fresh()->categories()->get()->pluck('id')->all());
        $this->assertCount(2, $section->fresh()->categories);
    }

    public function test_admin_cannot_select_inactive_categories(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->administrator());
        $section = HomeSection::query()->where('type', 'categories')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $category->update(['is_active' => false]);

        $this->put(route('admin.sections.selections', $section), [
            'selected' => [$category->id],
        ])->assertStatus(422);
    }

    public function test_admin_can_upload_banner_and_only_its_section_can_modify_it(): void
    {
        Storage::fake('public');
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->administrator());
        $section = HomeSection::query()->where('type', 'slider')->firstOrFail();
        $other = HomeSection::query()->where('type', 'banner')->firstOrFail();

        $png = UploadedFile::fake()->image('banner.png', 800, 450);
        $this->post(route('admin.slides.store', $section), [
            'desktop_image' => $png,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $new = $section->slides()->reorder()->orderByDesc('id')->firstOrFail();
        $this->assertStringStartsWith('storage/banners/', $new->desktop_image_path);
        Storage::disk('public')->assertExists(substr($new->desktop_image_path, 8));

        $this->patch(route('admin.slides.update', [$other, $new]), [
            'sort_order' => 1,
        ])->assertNotFound();

        $this->patch(route('admin.slides.update', [$section, $new]), [
            'sort_order' => 12,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('home_slides', ['id' => $new->id, 'sort_order' => 12]);
    }

    public function test_unsafe_uploaded_file_is_rejected(): void
    {
        Storage::fake('public');
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->administrator());
        $section = HomeSection::query()->where('type', 'slider')->firstOrFail();

        $this->post(route('admin.slides.store', $section), [
            'desktop_image' => UploadedFile::fake()->create('unsafe.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors('desktop_image');
    }
}
