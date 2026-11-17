<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PolarisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminHomeSectionActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_up_and_down_buttons_render_and_disable_at_boundaries(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->admin());

        $sections = HomeSection::orderBy('sort_order')->orderBy('id')->get();

        $html = $this->get(route('admin.index'))->assertOk()
            ->assertSee('admin-section-actions')
            ->assertSee('Bir sıra yukarı taşı')
            ->assertSee('Bir sıra aşağı taşı')
            ->assertSee('Bloğu sil')
            ->getContent();

        $this->assertSame($sections->count(), substr_count($html, 'name="direction" value="up"'));
        $this->assertSame($sections->count(), substr_count($html, 'name="direction" value="down"'));
        $this->assertSame($sections->count(), substr_count($html, 'name="_method" value="DELETE"'));
    }

    public function test_move_swaps_only_one_position_and_persists_even_with_tied_sort_values(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->admin());

        $sections = HomeSection::orderBy('sort_order')->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $sections->count());

        // Older sections may have duplicate positions from manual editing.
        $sections[0]->update(['sort_order' => 0]);
        $sections[1]->update(['sort_order' => 0]);

        $this->post(route('admin.sections.move', $sections[1]), ['direction' => 'up'])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $reordered = HomeSection::orderBy('sort_order')->orderBy('id')->get();
        $this->assertSame($sections[1]->id, $reordered[0]->id);
        $this->assertSame($sections[0]->id, $reordered[1]->id);
        $this->assertSame($sections[2]->id, $reordered[2]->id);
        $this->assertSame(range(0, $reordered->count() - 1), $reordered->pluck('sort_order')->all());

        $this->post(route('admin.sections.move', $sections[1]), ['direction' => 'down'])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $restored = HomeSection::orderBy('sort_order')->orderBy('id')->get();
        $this->assertSame($sections[0]->id, $restored[0]->id);
        $this->assertSame($sections[1]->id, $restored[1]->id);
        $this->assertSame($sections[2]->id, $restored[2]->id);
    }

    public function test_boundary_move_and_invalid_direction_do_not_change_order(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->admin());

        $sections = HomeSection::orderBy('sort_order')->orderBy('id')->get();
        $before = $sections->pluck('id')->all();

        $this->post(route('admin.sections.move', $sections->first()), ['direction' => 'up'])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->post(route('admin.sections.move', $sections->last()), ['direction' => 'down'])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $this->post(route('admin.sections.move', $sections[1]), ['direction' => 'invalid'])
            ->assertSessionHasErrors('direction');

        $this->assertSame($before, HomeSection::orderBy('sort_order')->orderBy('id')->pluck('id')->all());
    }

    public function test_deleting_a_product_block_removes_its_selection_not_the_products(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->admin());

        $section = HomeSection::where('type', HomeSection::TYPE_PRODUCTS)->firstOrFail();
        $productCount = Product::count();
        $categoryCount = Category::count();
        $id = $section->id;

        $this->assertGreaterThan(0, $section->products()->count());
        $this->delete(route('admin.sections.destroy', $section))
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('home_sections', ['id' => $id]);
        $this->assertDatabaseMissing('home_section_products', ['home_section_id' => $id]);
        $this->assertSame($productCount, Product::count());
        $this->assertSame($categoryCount, Category::count());
    }

    public function test_deleting_a_slider_cascades_only_its_slides(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $this->actingAs($this->admin());

        $section = HomeSection::where('type', HomeSection::TYPE_SLIDER)->firstOrFail();
        $section->slides()->create(['desktop_image_path' => 'assets/banner-test.svg', 'sort_order' => 100]);
        $slideCount = $section->slides()->count();
        $this->assertGreaterThan(0, $slideCount);
        $id = $section->id;

        $this->delete(route('admin.sections.destroy', $section))
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('home_sections', ['id' => $id]);
        $this->assertDatabaseMissing('home_slides', ['home_section_id' => $id]);
    }

    public function test_non_admin_cannot_move_or_delete_sections(): void
    {
        $this->seed(PolarisDemoSeeder::class);
        $section = HomeSection::firstOrFail();

        $this->post(route('admin.sections.move', $section), ['direction' => 'down'])
            ->assertRedirect(route('admin.login'));
        $this->delete(route('admin.sections.destroy', $section))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create());
        $this->post(route('admin.sections.move', $section), ['direction' => 'down'])
            ->assertForbidden();
        $this->delete(route('admin.sections.destroy', $section))
            ->assertForbidden();

        $this->assertDatabaseHas('home_sections', ['id' => $section->id]);
    }
}
