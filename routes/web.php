<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/kategori/{slug}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/urun/{slug}', [CatalogController::class, 'product'])->name('product.show');
Route::get('/ara', [CatalogController::class, 'search'])->name('search');

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CategoryImageController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\HomeSlideController;
use App\Http\Middleware\EnsureUserIsAdmin;

Route::get('/admin/login', [AdminAuthController::class, 'loginPage'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth')->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', [HomeSectionController::class, 'index'])->name('index');
    Route::post('/sections', [HomeSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [HomeSectionController::class, 'edit'])->name('sections.edit');
    Route::patch('/sections/{section}', [HomeSectionController::class, 'update'])->name('sections.update');
    Route::put('/sections/{section}/selections', [HomeSectionController::class, 'selection'])->name('sections.selections');
    Route::post('/sections/{section}/slides', [HomeSlideController::class, 'store'])->name('slides.store');
    Route::patch('/sections/{section}/slides/{slide}', [HomeSlideController::class, 'update'])->name('slides.update');
    Route::delete('/sections/{section}/slides/{slide}', [HomeSlideController::class, 'destroy'])->name('slides.destroy');
    Route::post('/categories/{category}/image', [CategoryImageController::class, 'update'])->name('categories.image');
});
