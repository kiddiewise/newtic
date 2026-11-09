<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/kategori/{slug}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/urun/{slug}', [CatalogController::class, 'product'])->name('product.show');
Route::get('/ara', [CatalogController::class, 'search'])->name('search');

// Session-backed shopping cart; order checkout uses the same authoritative cart.
Route::get('/favoriler', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/favoriler/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
Route::get('/sepet', [CartController::class, 'index'])->name('cart.index');
Route::post('/sepet/ekle', [CartController::class, 'add'])->name('cart.add');
Route::patch('/sepet/urun/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/sepet/urun/{product}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/sepet/temizle', [CartController::class, 'clear'])->name('cart.clear');

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CheckoutController;

Route::get('/giris', [AccountController::class, 'loginPage'])->name('account.login');
Route::post('/giris', [AccountController::class, 'login'])->middleware('throttle:6,1')->name('account.login.submit');
Route::get('/uye-ol', [AccountController::class, 'registerPage'])->name('account.register');
Route::post('/uye-ol', [AccountController::class, 'register'])->middleware('throttle:5,1')->name('account.register.submit');
Route::post('/cikis', [AccountController::class, 'logout'])->middleware('auth')->name('account.logout');

Route::get('/odeme', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/odeme/siparis', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
Route::get('/siparis/onay/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::prefix('hesabim')->name('account.')->middleware('auth')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/adresler', [CustomerAddressController::class, 'index'])->name('addresses.index');
    Route::get('/adresler/yeni', [CustomerAddressController::class, 'create'])->name('addresses.create');
    Route::post('/adresler', [CustomerAddressController::class, 'store'])->name('addresses.store');
    Route::get('/adresler/{address}/duzenle', [CustomerAddressController::class, 'edit'])->name('addresses.edit');
    Route::patch('/adresler/{address}', [CustomerAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/adresler/{address}', [CustomerAddressController::class, 'destroy'])->name('addresses.destroy');
});

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\CategoryBannerController;
use App\Http\Controllers\Admin\CategoryImageController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\HomeSlideController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StoreSettingController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Middleware\EnsureUserIsAdmin;

Route::get('/admin/login', [AdminAuthController::class, 'loginPage'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth')->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', [HomeSectionController::class, 'index'])->name('index');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::get('/kargo-ayarlari', [StoreSettingController::class, 'edit'])->name('store-settings.edit');
    Route::patch('/kargo-ayarlari', [StoreSettingController::class, 'update'])->name('store-settings.update');
    Route::get('/logo-ayarlari', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('/logo-ayarlari', [AppearanceController::class, 'update'])->name('appearance.update');
    Route::get('/duyurular', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/duyurular', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('/duyurular/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/duyurular/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/banners', [CategoryBannerController::class, 'store'])->name('categories.banners.store');
    Route::patch('/categories/{category}/banners/{banner}', [CategoryBannerController::class, 'update'])->name('categories.banners.update');
    Route::delete('/categories/{category}/banners/{banner}', [CategoryBannerController::class, 'destroy'])->name('categories.banners.destroy');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage'])->name('products.images.destroy');

    Route::post('/sections', [HomeSectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/{section}/edit', [HomeSectionController::class, 'edit'])->name('sections.edit');
    Route::patch('/sections/{section}', [HomeSectionController::class, 'update'])->name('sections.update');
    Route::put('/sections/{section}/selections', [HomeSectionController::class, 'selection'])->name('sections.selections');
    Route::post('/sections/{section}/slides', [HomeSlideController::class, 'store'])->name('slides.store');
    Route::patch('/sections/{section}/slides/{slide}', [HomeSlideController::class, 'update'])->name('slides.update');
    Route::delete('/sections/{section}/slides/{slide}', [HomeSlideController::class, 'destroy'])->name('slides.destroy');
    Route::post('/categories/{category}/image', [CategoryImageController::class, 'update'])->name('categories.image');
});
