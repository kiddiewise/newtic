<?php

namespace App\Http\Controllers;

use App\Models\HomeSection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $sections = HomeSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'slides' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'categories' => fn ($q) => $q->where('is_active', true),
                'products' => fn ($q) => $q->where('is_active', true),
            ])
            ->get();

        return view('store.home', compact('sections'));
    }
}
