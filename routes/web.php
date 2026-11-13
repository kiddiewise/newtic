<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentNotificationController;
use App\Http\Controllers\PaytrCallbackController;
use App\Http\Controllers\ShippingCallbackController;
use App\Http\Controllers\PaytrCheckoutController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\GoogleMerchantFeedController;
use App\Http\Controllers\SeoSitemapController;
use App\Http\Controllers\Admin\SeoSettingsController;
use App\Http\Controllers\Admin\GoogleMerchantController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MegaMenuSettingsController;

Route::get('/', HomeController::class)->name('home');
Route::get('/robots.txt', [SeoSitemapController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoSitemapController::class, 'index'])->name('seo.sitemap');
Route::get('/sitemap/pages.xml', [SeoSitemapController::class, 'pages'])->name('seo.sitemap.pages');
Route::get('/sitemap/categories.xml', [SeoSitemapController::class, 'categories'])->name('seo.sitemap.categories');
Route::get('/sitemap/products/{page}.xml', [SeoSitemapController::class, 'products'])
    ->whereNumber('page')->name('seo.sitemap.products');
Route::get('/google/merchant-products.xml', GoogleMerchantFeedController::class)
    ->middleware('throttle:30,1')->name('google-merchant.feed');
Route::get('/kategori/{slug}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/urun/{slug}', [CatalogController::class, 'product'])->name('product.show');
Route::get('/ara/oneriler', [CatalogController::class, 'suggestions'])->middleware('throttle:60,1')->name('search.suggest');
Route::get('/ara', [CatalogController::class, 'search'])->name('search');

// Session-backed shopping cart; order checkout uses the same authoritative cart.
// Favoriting and the wishlist require membership; cart stays available to guests.
Route::middleware('auth')->group(function () {
    Route::get('/favoriler', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/favoriler/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::post('/urun/{product:slug}/yorum', [ProductReviewController::class, 'store'])
        ->middleware('throttle:4,1')->name('product.reviews.store');
});
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

// Separate, sessionless endpoint. Explicit server-side gate remains OFF by default.
Route::post('/kargo/bildirim', ShippingCallbackController::class)->middleware('throttle:120,1')->name('shipping.callback');

Route::post('/odeme/paytr/bildirim', PaytrCallbackController::class)
    ->name('checkout.paytr.callback');

Route::get('/odeme/paytr/{order}', [PaytrCheckoutController::class, 'show'])->middleware('throttle:8,1')->name('checkout.paytr.frame');
Route::get('/odeme', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/odeme/siparis', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
Route::get('/siparis/onay/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/siparis/onay/{order}/odeme-bildir', [PaymentNotificationController::class, 'store'])
    ->middleware('throttle:5,1')->name('checkout.payment-notify');

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
use App\Http\Controllers\Admin\ProductCsvController;
use App\Http\Controllers\Admin\ProductBadgeController;
use App\Http\Controllers\Admin\CatalogImportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StoreSettingController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\PaytrSettingsController;
use App\Http\Controllers\Admin\ShippingIntegrationController;
use App\Http\Controllers\Admin\ShippingShipmentController;
use App\Http\Controllers\Admin\ShippingOperationsController;
use App\Http\Controllers\Admin\PaymentNotificationController as AdminPaymentNotificationController;
use App\Http\Controllers\Admin\ProductReviewController as AdminProductReviewController;
use App\Http\Middleware\EnsureUserIsAdmin;

Route::get('/admin/login', [AdminAuthController::class, 'loginPage'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')->name('admin.login.submit');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])
    ->middleware('auth')->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/', [HomeSectionController::class, 'index'])->name('index');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/seo-ayarlari', [SeoSettingsController::class, 'edit'])->name('seo.edit');
    Route::patch('/seo-ayarlari', [SeoSettingsController::class, 'update'])
        ->middleware('throttle:10,1')->name('seo.update');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/stock', StockController::class)->name('stock');
    Route::get('/customers', CustomerController::class)->name('customers');
    Route::get('/reviews', [AdminProductReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/moderate', [AdminProductReviewController::class, 'moderate'])->name('reviews.moderate');
    Route::get('/reports', ReportController::class)->name('reports');
    Route::get('/odeme-ayarlari/paytr', [PaytrSettingsController::class, 'edit'])->name('paytr-settings.edit');
    Route::patch('/odeme-ayarlari/paytr', [PaytrSettingsController::class, 'update'])->middleware('throttle:10,1')->name('paytr-settings.update');
    Route::post('/odeme-ayarlari/paytr/test-modu', [PaytrSettingsController::class, 'testMode'])->middleware('throttle:6,1')->name('paytr-settings.test-mode');
    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::patch('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
    Route::get('/payment-notifications', [AdminPaymentNotificationController::class, 'index'])->name('payment-notifications.index');
    Route::post('/payment-notifications/{paymentNotification}/verify', [AdminPaymentNotificationController::class, 'verify'])->name('payment-notifications.verify');
    Route::post('/payment-notifications/{paymentNotification}/reject', [AdminPaymentNotificationController::class, 'reject'])->name('payment-notifications.reject');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::patch('/orders/{order}/status-correction', [AdminOrderController::class, 'correct'])->middleware('throttle:8,1')->name('orders.correct');
    Route::post('/orders/{order}/bank-verification-correction', [AdminOrderController::class, 'revokeBankVerification'])->middleware('throttle:4,1')->name('orders.revoke-bank-verification');
    Route::patch('/orders/{order}/shipping', [AdminOrderController::class, 'shipping'])->name('orders.shipping');
    Route::get('/kargo-operasyonlari', [ShippingOperationsController::class, 'index'])->name('shipping-operations.index');
    Route::post('/kargo-operasyonlari/kontrol', [ShippingOperationsController::class, 'sync'])->middleware('throttle:4,1')->name('shipping-operations.sync');
    Route::post('/kargo-operasyonlari/{shipment}/inceleme', [ShippingOperationsController::class, 'review'])->middleware('throttle:12,1')->name('shipping-operations.review');
    Route::get('/kargo-entegrasyonu', [ShippingIntegrationController::class, 'index'])->name('shipping-integrations.index');
    Route::patch('/kargo-entegrasyonu', [ShippingIntegrationController::class, 'save'])->middleware('throttle:8,1')->name('shipping-integrations.save');
    Route::post('/kargo-entegrasyonu/senkronize', [ShippingIntegrationController::class, 'sync'])->middleware('throttle:10,1')->name('shipping-integrations.sync');
    Route::post('/kargo-entegrasyonu/test', [ShippingIntegrationController::class, 'toggle'])->middleware('throttle:8,1')->name('shipping-integrations.toggle');
    Route::post('/orders/{order}/shipping-integration', [ShippingShipmentController::class, 'create'])->middleware('throttle:4,1')->name('shipping-shipments.create');
    Route::post('/orders/{order}/shipping-integration/refresh', [ShippingShipmentController::class, 'refresh'])->middleware('throttle:10,1')->name('shipping-shipments.refresh');
    Route::get('/orders/{order}/shipping-integration/label', [ShippingShipmentController::class, 'label'])->middleware('throttle:12,1')->name('shipping-shipments.label');
    Route::get('/kargo-ayarlari', [StoreSettingController::class, 'edit'])->name('store-settings.edit');
    Route::patch('/kargo-ayarlari', [StoreSettingController::class, 'update'])->name('store-settings.update');
    Route::get('/menuler/mega-menu-ayarlari', [MegaMenuSettingsController::class, 'edit'])->name('menus.mega-menu.edit');
    Route::patch('/menuler/mega-menu-ayarlari', [MegaMenuSettingsController::class, 'update'])->middleware('throttle:10,1')->name('menus.mega-menu.update');
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

    Route::get('/products/import', [CatalogImportController::class, 'index'])->name('product-imports.index');
    Route::post('/products/import', [CatalogImportController::class, 'upload'])->middleware('throttle:10,1')->name('product-imports.upload');
    Route::get('/products/import/{catalogImport}', [CatalogImportController::class, 'show'])->name('product-imports.show');
    Route::post('/products/import/{catalogImport}/start', [CatalogImportController::class, 'start'])->name('product-imports.start');
    Route::post('/products/import/{catalogImport}/step', [CatalogImportController::class, 'step'])->name('product-imports.step');
    Route::get('/urun-rozetleri', [ProductBadgeController::class, 'index'])->name('product-badges.index');
    Route::post('/urun-rozetleri', [ProductBadgeController::class, 'store'])->name('product-badges.store');
    Route::patch('/urun-rozetleri/{badge}', [ProductBadgeController::class, 'update'])->name('product-badges.update');
    Route::delete('/urun-rozetleri/{badge}', [ProductBadgeController::class, 'destroy'])->name('product-badges.destroy');
    Route::get('/products/bulk', [ProductCsvController::class, 'index'])->name('products.bulk.index');
    Route::get('/products/bulk/export', [ProductCsvController::class, 'export'])->middleware('throttle:5,1')->name('products.bulk.export');
    Route::get('/products/bulk/template', [ProductCsvController::class, 'template'])->name('products.bulk.template');
    Route::post('/products/bulk', [ProductCsvController::class, 'upload'])->middleware('throttle:5,1')->name('products.bulk.upload');
    Route::get('/google-merchant', [GoogleMerchantController::class, 'edit'])->name('google-merchant.edit');
    Route::patch('/google-merchant', [GoogleMerchantController::class, 'save'])->middleware('throttle:6,1')->name('google-merchant.save');
    Route::post('/google-merchant/connect', [GoogleMerchantController::class, 'connect'])->middleware('throttle:6,1')->name('google-merchant.connect');
    Route::get('/google-merchant/callback', [GoogleMerchantController::class, 'callback'])->middleware('throttle:6,1')->name('google-merchant.callback');
    Route::post('/google-merchant/accounts', [GoogleMerchantController::class, 'accounts'])->middleware('throttle:6,1')->name('google-merchant.accounts');
    Route::post('/google-merchant/account', [GoogleMerchantController::class, 'selectAccount'])->middleware('throttle:6,1')->name('google-merchant.select');
    Route::post('/google-merchant/source', [GoogleMerchantController::class, 'prepare'])->middleware('throttle:4,1')->name('google-merchant.prepare');
    Route::patch('/google-merchant/auto-sync', [GoogleMerchantController::class, 'toggleAutoSync'])->name('google-merchant.auto-sync');
    Route::post('/google-merchant/disconnect', [GoogleMerchantController::class, 'disconnect'])->middleware('throttle:6,1')->name('google-merchant.disconnect');
    Route::post('/google-merchant/sync/start', [GoogleMerchantController::class, 'start'])->middleware('throttle:6,1')->name('google-merchant.start');
    Route::post('/google-merchant/sync/step', [GoogleMerchantController::class, 'step'])->middleware('throttle:40,1')->name('google-merchant.step');
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
