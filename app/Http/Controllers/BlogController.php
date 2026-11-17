<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()->with('category')
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(9);

        return view('store.blog.index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()->where('is_active', true)
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'selectedCategory' => null,
        ]);
    }

    public function category(string $slug): View
    {
        $category = BlogCategory::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $posts = BlogPost::published()->with('category')
            ->where('blog_category_id', $category->id)
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(9);

        return view('store.blog.index', [
            'posts' => $posts,
            'categories' => BlogCategory::query()->where('is_active', true)
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'selectedCategory' => $category,
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::published()->where('slug', $slug)
            ->with(['category', 'blocks.images'])->firstOrFail();

        $related = BlogPost::published()->whereKeyNot($post->id)
            ->when($post->blog_category_id, fn ($q) => $q->where('blog_category_id', $post->blog_category_id))
            ->orderByDesc('published_at')->limit(3)->get();

        return view('store.blog.show', compact('post', 'related'));
    }
}
