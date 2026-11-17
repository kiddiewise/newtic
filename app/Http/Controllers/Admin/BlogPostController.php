<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BlogPostController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.index', [
            'posts' => BlogPost::query()->with('category')->latest('updated_at')->paginate(20),
            'publishedCount' => BlogPost::published()->count(),
            'draftCount' => BlogPost::where('is_published', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new BlogPost(),
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['slug'] = $this->slug($data, null);
        $data['is_published'] = $request->boolean('is_published');
        $data['seo_noindex'] = $request->boolean('seo_noindex');
        $data['published_at'] = $data['is_published'] ? now() : null;
        if ($request->hasFile('featured_image')) {
            $data['featured_image_path'] = 'storage/'.$request->file('featured_image')->store('blog', 'public');
        }
        unset($data['featured_image'], $data['remove_featured_image']);
        $post = BlogPost::create($data);

        return redirect()->route('admin.blog.posts.edit', $post)
            ->with('success', 'Yazı oluşturuldu. Aşağıdan içerik blokları ekleyebilirsiniz.');
    }

    public function edit(BlogPost $post): View
    {
        $post->load('blocks.images', 'category');
        return view('admin.blog.form', [
            'post' => $post,
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $data = $this->validated($request, $post);
        $data['slug'] = $this->slug($data, $post);
        $data['is_published'] = $request->boolean('is_published');
        $data['seo_noindex'] = $request->boolean('seo_noindex');
        $data['published_at'] = $data['is_published'] ? ($post->published_at ?? now()) : $post->published_at;
        $oldImage = null;
        if ($request->hasFile('featured_image')) {
            $newImage = 'storage/'.$request->file('featured_image')->store('blog', 'public');
            $oldImage = $post->featured_image_path;
            $data['featured_image_path'] = $newImage;
        } elseif ($request->boolean('remove_featured_image')) {
            $oldImage = $post->featured_image_path;
            $data['featured_image_path'] = null;
        }
        unset($data['featured_image'], $data['remove_featured_image']);
        $post->update($data);
        $this->deleteMedia($oldImage);

        return back()->with('success', 'Yazı ve SEO ayarları kaydedildi.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $paths = array_merge([$post->featured_image_path],
            $post->blocks()->with('images')->get()
                ->flatMap(fn ($block) => $block->images->pluck('path'))->all());
        $post->delete();
        foreach ($paths as $path) $this->deleteMedia($path);
        return redirect()->route('admin.blog.posts.index')->with('success', 'Yazı kaldırıldı.');
    }

    private function validated(Request $request, ?BlogPost $post): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_image_alt' => ['nullable', 'string', 'max:190'],
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,avif',
                'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_noindex' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'remove_featured_image' => ['sometimes', 'boolean'],
            'sources' => ['nullable', 'array', 'max:20'],
            'sources.*.title' => ['nullable', 'string', 'max:160'],
            'sources.*.url' => ['nullable', 'url:http,https', 'max:1500'],
        ]);
        $sources = [];
        foreach ($data['sources'] ?? [] as $i => $source) {
            $title = trim((string) ($source['title'] ?? ''));
            $url = trim((string) ($source['url'] ?? ''));
            if (!$title && !$url) continue;
            if (!$title || !$url) {
                throw ValidationException::withMessages([
                    'sources.'.$i.'.url' => 'Her kaynak için hem başlık hem geçerli bağlantı giriniz.',
                ]);
            }
            $sources[] = ['title' => $title, 'url' => $url];
        }
        $data['sources'] = $sources;

        return $data;
    }

    private function slug(array $data, ?BlogPost $post): string
    {
        if (filled($data['slug'] ?? null)) return $data['slug'];
        if ($post && $post->slug) return $post->slug;
        $base = Str::slug($data['title']) ?: 'yazi';
        $base = mb_substr($base, 0, 160);
        $slug = $base;
        for ($i = 2; BlogPost::where('slug', $slug)->exists(); ++$i) $slug = $base.'-'.$i;
        return $slug;
    }

    private function deleteMedia(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/blog/')) {
            Storage::disk('public')->delete(substr($path, 8));
        }
    }
}
