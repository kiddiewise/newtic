<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryImageController extends Controller
{
    public function update(Request $request, Category $category): RedirectResponse
    {
        $request->validate([
            'image' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp,avif',
                'max:4096', 'dimensions:max_width=3000,max_height=3000',
            ],
        ]);

        $path = $request->file('image')->store('categories', 'public');
        $category->update(['image_path' => 'storage/'.$path]);

        return back()->with('success', 'Kategori görseli güncellendi.');
    }
}
