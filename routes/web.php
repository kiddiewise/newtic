<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/kategori/{slug}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/urun/{slug}', [CatalogController::class, 'product'])->name('product.show');
Route::get('/ara', [CatalogController::class, 'search'])->name('search');
