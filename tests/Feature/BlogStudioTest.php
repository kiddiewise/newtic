<?php

namespace Tests\Feature;

use App\Models\BlogBlock;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\SeoSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function category(): BlogCategory
    {
        return BlogCategory::create([
            'name' => 'Kıyı Avı', 'slug' => 'kiyi-avi',
            'description' => 'Kıyı avı rehberleri', 'is_active' => true,
        ]);
    }

    private function post(BlogCategory $category, bool $published = false): BlogPost
    {
        return BlogPost::create([
            'title' => 'Kıyıdan Avlanmanın İncelikleri',
            'slug' => 'kiyidan-avlanmanin-incelikleri',
            'blog_category_id' => $category->id,
            'excerpt' => 'Kıyı balıkçılığı hakkında faydalı bir rehber.',
            'is_published' => $published,
            'published_at' => $published ? now()->subMinute() : null,
        ]);
    }

    public function test_empty_blog_has_no_fake_articles_or_homepage_cards(): void
    {
        $this->get(route('blog.index'))->assertOk()
            ->assertSee('Henüz yayınlanmış yazı yok.')
            ->assertDontSee('blog-card-copy');
        $this->get(route('home'))->assertOk()->assertDontSee('Blogdan son yazılar');
        $this->get(route('admin.blog.posts.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.blog.categories.store'), ['name' => 'Yetkisiz'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_cannot_manage_blog(): void
    {
        $category = $this->category();
        $post = $this->post($category);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.blog.posts.index'))->assertForbidden();
        $this->get(route('admin.blog.posts.edit', $post))->assertForbidden();
        $this->patch(route('admin.blog.posts.update', $post), ['title' => 'Hack'])->assertForbidden();
        $this->delete(route('admin.blog.posts.destroy', $post))->assertForbidden();
        $this->post(route('admin.blog.blocks.store', $post), ['type' => 'text', 'content' => 'Hack'])
            ->assertForbidden();
    }

    public function test_editor_creates_draft_then_publishes_with_optional_sources_and_seo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->post(route('admin.blog.categories.store'), ['name' => 'Balıkçılık Rehberi', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $category = BlogCategory::firstOrFail();
        $this->assertSame('balikcilik-rehberi', $category->slug);

        $this->post(route('admin.blog.posts.store'), [
            'title' => 'Balıkçılık Ekipmanları Rehberi',
            'blog_category_id' => $category->id,
            'excerpt' => 'Olta ve malzeme önerileri.',
            'featured_image_alt' => 'Balıkçılık fotoğrafı',
            'featured_image' => UploadedFile::fake()->image('kapak.webp', 1600, 900),
            'seo_title' => 'Ekipman Rehberi - Test',
            'seo_description' => 'En uygun malzemelerin seçimi için rehber.',
            'sources' => [
                ['title' => 'Kaynak bilgi', 'url' => 'https://example.com/guide'],
                ['title' => '', 'url' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $post = BlogPost::firstOrFail();
        $this->assertSame('balikcilik-ekipmanlari-rehberi', $post->slug);
        $this->assertFalse($post->is_published);
        $this->assertCount(1, $post->sources);
        Storage::disk('public')->assertExists(substr($post->featured_image_path, 8));

        $this->get(route('blog.show', $post->slug))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee($post->title);

        $this->post(route('admin.blog.blocks.store', $post), [
            'type' => 'heading', 'content' => 'Malzemeler nasıl seçilir?',
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.blog.blocks.store', $post), [
            'type' => 'text', 'content' => "Birinci paragraf.\n<script>alert('XSS')</script>",
        ])->assertSessionHasNoErrors();

        $this->patch(route('admin.blog.posts.update', $post), [
            'title' => $post->title,
            'is_published' => '1',
            'seo_title' => 'Ekipman Rehberi - Test',
            'seo_description' => 'En uygun malzemelerin seçimi için rehber.',
            'sources' => [['title' => 'Kaynak bilgi', 'url' => 'https://example.com/guide']],
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertTrue($post->is_published);
        $this->assertNotNull($post->published_at);

        $html = $this->get('https://shop.example/blog/'.$post->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Ekipman Rehberi - Test', $html);
        $this->assertStringContainsString('Malzemeler nasıl seçilir?', $html);
        $this->assertStringContainsString('Kaynak bilgi', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('XSS')</script>", $html);
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
        $this->assertStringContainsString('https://shop.example/blog/'.$post->slug, $html);
        $this->get('https://shop.example/')->assertSee('Blogdan son yazılar');
    }

    public function test_visual_block_slider_reordering_and_media_cleanup(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $post = $this->post($category, true);
        $this->actingAs($this->admin());

        $this->post(route('admin.blog.blocks.store', $post), [
            'type' => 'slider',
            'images' => [
                UploadedFile::fake()->image('birinci.jpg', 1600, 900),
                UploadedFile::fake()->image('ikinci.jpg', 1600, 900),
            ],
            'alt' => 'Olta hazırlığı',
        ])->assertSessionHasNoErrors();

        $slider = BlogBlock::firstOrFail();
        $images = $slider->images()->get();
        $this->assertCount(2, $images);
        foreach ($images as $image) Storage::disk('public')->assertExists(substr($image->path, 8));

        $this->post(route('admin.blog.blocks.store', $post), [
            'type' => 'heading', 'content' => 'Sonraki bölüm',
        ])->assertSessionHasNoErrors();

        $heading = BlogBlock::where('type', 'heading')->firstOrFail();
        $this->post(route('admin.blog.blocks.move', [$post, $heading]), [
            'direction' => 'up',
        ])->assertSessionHasNoErrors();
        $this->assertSame($heading->id, $post->blocks()->first()->id);

        $this->patch(route('admin.blog.images.update', [$post, $slider, $images[1]]), [
            'sort_order' => 0, 'alt' => 'Güncellenen açıklama',
            'caption' => 'Güncel fotoğraf',
        ])->assertSessionHasNoErrors();

        $this->get(route('blog.show', $post->slug))->assertOk()
            ->assertSee('data-blog-gallery')
            ->assertSee('Güncellenen açıklama')
            ->assertSee('Güncel fotoğraf');

        $this->delete(route('admin.blog.images.destroy', [$post, $slider, $images[0]]))
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($images[0]->path, 8));

        $this->delete(route('admin.blog.blocks.destroy', [$post, $slider]))
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($images[1]->path, 8));
        $this->assertDatabaseMissing('blog_blocks', ['id' => $slider->id]);
    }

    public function test_sources_and_uploaded_html_are_validated_and_post_category_permissions_enforced(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.blog.posts.store'), [
            'title' => 'Kaynaklı Yazı',
            'sources' => [['title' => 'Kaynak', 'url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors('sources.0.url');

        $this->post(route('admin.blog.posts.store'), [
            'title' => 'Kaynaklı Yazı',
            'sources' => [['title' => 'Kaynak', 'url' => '']],
        ])->assertSessionHasErrors('sources.0.url');

        $this->post(route('admin.blog.posts.store'), [
            'title' => 'Güvenli Yazı',
        ])->assertSessionHasNoErrors();
        $post = BlogPost::firstOrFail();

        $this->post(route('admin.blog.blocks.store', $post), [
            'type' => 'slider',
            'images' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')],
        ])->assertSessionHasErrors('images.0');

        $other = BlogPost::create(['title' => 'Diğer Yazı', 'slug' => 'diger-yazi']);
        $block = $post->blocks()->create(['type' => 'heading', 'content' => 'Gizli', 'sort_order' => 1]);
        $this->delete(route('admin.blog.blocks.destroy', [$other, $block]))->assertNotFound();
        $this->assertDatabaseHas('blog_blocks', ['id' => $block->id]);
    }

    public function test_indexing_sitemap_excludes_drafts_noindex_posts_and_inactive_categories(): void
    {
        SeoSetting::current()->update(['noindex' => false]);
        $category = $this->category();
        $post = $this->post($category, true);
        $draft = BlogPost::create(['title' => 'Taslak', 'slug' => 'taslak', 'is_published' => false]);
        $hidden = BlogPost::create([
            'title' => 'İndekslenmez', 'slug' => 'indekslenmez',
            'is_published' => true, 'published_at' => now()->subMinute(), 'seo_noindex' => true,
        ]);
        $map = $this->get('https://shop.example/sitemap/blog.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/blog/'.$post->slug, $map);
        $this->assertStringNotContainsString('/blog/'.$draft->slug, $map);
        $this->assertStringNotContainsString('/blog/'.$hidden->slug, $map);

        $html = $this->get('https://shop.example/blog/'.$hidden->slug)->assertOk()->getContent();
        $this->assertStringContainsString('noindex,nofollow,noarchive', $html);

        $category->update(['is_active' => false]);
        $this->get(route('blog.show', $post->slug))->assertNotFound();
        $this->get(route('blog.category', $category->slug))->assertNotFound();
        $this->get('https://powderblue-dinosaur-538060.hostingersite.com/sitemap/blog.xml')
            ->assertNotFound();
    }

    public function test_admin_image_size_recommendations_are_visible(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.appearance.edit'))->assertOk()->assertSee('800 × 200 px')->assertSee('320 × 320 px');
        $this->get(route('admin.blog.posts.create'))->assertOk()->assertSee('1600 × 900 px');
    }
}
