<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogBlock;
use App\Models\BlogBlockImage;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BlogBlockController extends Controller
{
    public function store(Request $request, BlogPost $post): RedirectResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(BlogBlock::TYPES)]])['type'];
        $data = $this->validated($request, $type, true, 0);
        $block = $post->blocks()->create([
            'type' => $type, 'content' => $data['content'] ?? null,
            'sort_order' => ((int) $post->blocks()->max('sort_order')) + 1,
        ]);
        $this->upload($request, $block, $data);
        return redirect()->route('admin.blog.posts.edit', $post)
            ->with('success', 'İçerik bloğu eklendi.');
    }

    public function update(Request $request, BlogPost $post, BlogBlock $block): RedirectResponse
    {
        $this->ownership($post, $block);
        $data = $this->validated($request, $block->type, false, $block->images()->count());
        $block->update(['content' => $data['content'] ?? null]);
        $this->upload($request, $block, $data);
        return back()->with('success', 'İçerik bloğu güncellendi.');
    }

    public function move(Request $request, BlogPost $post, BlogBlock $block): RedirectResponse
    {
        $this->ownership($post, $block);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        DB::transaction(function () use ($post, $block, $data) {
            $blocks = BlogBlock::query()->where('blog_post_id', $post->id)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $blocks->search(fn ($candidate) => $candidate->id === $block->id);
            $next = $index + ($data['direction'] === 'up' ? -1 : 1);
            if ($index === false || $next < 0 || $next >= $blocks->count()) return;
            [$blocks[$index], $blocks[$next]] = [$blocks[$next], $blocks[$index]];
            foreach ($blocks as $position => $candidate) {
                if ($candidate->sort_order !== $position) $candidate->update(['sort_order' => $position]);
            }
        });
        return back()->with('success', 'Blok sırası otomatik kaydedildi.');
    }

    public function destroy(BlogPost $post, BlogBlock $block): RedirectResponse
    {
        $this->ownership($post, $block);
        $paths = $block->images()->pluck('path')->all();
        $block->delete();
        foreach ($paths as $path) $this->deleteMedia($path);
        return back()->with('success', 'İçerik bloğu silindi.');
    }

    public function updateImage(Request $request, BlogPost $post, BlogBlock $block, BlogBlockImage $image): RedirectResponse
    {
        $this->imageOwnership($post, $block, $image);
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:190'],
            'caption' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $image->update($data);
        return back()->with('success', 'Görsel bilgileri kaydedildi.');
    }

    public function destroyImage(BlogPost $post, BlogBlock $block, BlogBlockImage $image): RedirectResponse
    {
        $this->imageOwnership($post, $block, $image);
        $path = $image->path;
        $image->delete();
        $this->deleteMedia($path);
        return back()->with('success', 'Görsel kaldırıldı.');
    }

    private function ownership(BlogPost $post, BlogBlock $block): void
    {
        abort_unless($block->blog_post_id === $post->id, 404);
    }

    private function imageOwnership(BlogPost $post, BlogBlock $block, BlogBlockImage $image): void
    {
        $this->ownership($post, $block);
        abort_unless($image->blog_block_id === $block->id, 404);
    }

    private function validated(Request $request, string $type, bool $creating, int $currentImages): array
    {
        $data = $request->validate([
            'content' => [in_array($type, ['heading', 'text'], true) ? 'required' : 'nullable',
                'string', $type === 'heading' ? 'max:190' : 'max:30000'],
            'images' => [($creating && in_array($type, ['image', 'slider'], true)) ? 'required' : 'nullable',
                'array', 'max:12'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120',
                'dimensions:max_width=6000,max_height=6000'],
            'alt' => ['nullable', 'string', 'max:190'],
            'caption' => ['nullable', 'string', 'max:300'],
        ]);
        $newImages = count($request->file('images', []));
        $limit = $type === 'image' ? 1 : 12;
        if (in_array($type, ['image', 'slider'], true) && $newImages + ($type === 'image' ? 0 : $currentImages) > $limit) {
            throw ValidationException::withMessages(['images' => 'Bu görsel bloğu için dosya sınırına ulaşıldı.']);
        }
        return $data;
    }

    private function upload(Request $request, BlogBlock $block, array $data): void
    {
        $uploads = $request->file('images', []);
        if (!in_array($block->type, ['image', 'slider'], true) || !$uploads) return;

        $paths = [];
        try {
            foreach ($uploads as $file) $paths[] = 'storage/'.$file->store('blog/blocks', 'public');

            if ($block->type === 'image') {
                $old = $block->images()->pluck('path')->all();
                $block->images()->delete();
                foreach ($old as $path) $this->deleteMedia($path);
            }
            $start = ((int) $block->images()->max('sort_order')) + 1;
            foreach ($paths as $i => $path) {
                $block->images()->create([
                    'path' => $path, 'alt' => $data['alt'] ?? null,
                    'caption' => $data['caption'] ?? null, 'sort_order' => $start + $i,
                ]);
            }
        } catch (\Throwable $e) {
            foreach ($paths as $path) $this->deleteMedia($path);
            throw $e;
        }
    }

    private function deleteMedia(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/blog/')) {
            Storage::disk('public')->delete(substr($path, 8));
        }
    }
}
