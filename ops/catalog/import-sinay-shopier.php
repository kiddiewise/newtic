<?php

declare(strict_types=1);

// One-time Sinay merchant catalog operation. NEVER included in generic NEWTIC
// database seeding and NEVER executed on Polaris or another merchant domain.
// Usage from /home/u880685189/domains/sinaydesign.com/newtic:
// php /private/import-shopier.php /private/products.json --dry-run|--apply

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$exit = static function (string $message, int $code = 2): never {
    fwrite(STDERR, 'SINAY_CATALOG_ABORT: '.$message.PHP_EOL);
    exit($code);
};

if (count($argv) !== 3 || !in_array($argv[2], ['--dry-run', '--apply'], true)) {
    $exit('Expected a catalog JSON file and --dry-run/--apply.');
}

$appRoot = getcwd();
if (!is_string($appRoot) || !is_file($appRoot.'/artisan') || !is_file($appRoot.'/vendor/autoload.php')) {
    $exit('Run from the existing Sinay Design Laravel application root.');
}
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targetUrl = (string) config('app.url');
$databaseName = (string) DB::connection()->getDatabaseName();
if ($targetUrl !== 'https://sinaydesign.com'
    || $databaseName !== 'u880685189_sinay_newtic'
    || config('app.env') !== 'production'
    || !is_file(storage_path('app/private/newtic-setup/installed.lock'))) {
    $exit('Incorrect merchant, database or installation state.');
}
if (config('newtic.checkout_test_mode') !== true
    || config('newtic.paytr_card_payments_enabled') !== false) {
    $exit('Sinay environment has changed; review live payment safety before importing.');
}

$raw = @file_get_contents($argv[1]);
if ($raw === false) $exit('Catalog file unreadable.');
try {
    $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $exit('Catalog JSON is invalid.');
}
if (!is_array($input)
    || ($input['merchant'] ?? '') !== 'sinaydesign.com'
    || ($input['source'] ?? '') !== 'https://www.shopier.com/sinayy'
    || ($input['category'] ?? '') !== 'Hediyelikler'
    || ($input['currency'] ?? '') !== 'TRY'
    || !is_array($input['products'] ?? null)
    || count($input['products']) !== 24) {
    $exit('Catalog provenance, currency, merchant or expected product count differs.');
}

$rows = [];
foreach ($input['products'] as $index => $item) {
    $seq = $index + 1;
    if (!is_array($item) || ($item['sequence'] ?? null) !== $seq
        || !is_string($item['name'] ?? null)
        || trim($item['name']) === ''
        || mb_strlen($item['name']) > 190
        || !is_int($item['price_try'] ?? null)
        || $item['price_try'] <= 0) {
        $exit('Invalid Shopier product at sequence '.$seq);
    }

    $name = trim($item['name']);
    $sku = sprintf('SIN-SHOPIER-202611-%03d', $seq);
    $slug = sprintf('shopier-hediyelik-%03d-%s', $seq, mb_substr(Str::slug($name), 0, 130));
    $rows[] = [
        'name' => $name,
        'sku' => $sku,
        'slug' => $slug,
        'price' => number_format($item['price_try'], 2, '.', ''),
    ];
}

$existingCategory = Category::query()->where('slug', 'hediyelikler')->first();
$sameName = Category::query()->where('name', 'Hediyelikler')->whereNull('parent_id')->first();
if ($existingCategory && $existingCategory->name !== 'Hediyelikler') {
    $exit('Slug hediyelikler belongs to another existing category.');
}
if (!$existingCategory && $sameName && $sameName->slug !== 'hediyelikler') {
    $exit('Hediyelikler category has an unexpected existing slug.');
}
$category = $existingCategory ?? $sameName;
if ($category && $category->parent_id !== null) {
    $exit('Existing Hediyelikler is not a root category.');
}

$existingCount = 0;
foreach ($rows as $item) {
    $current = Product::query()->where('sku', $item['sku'])->first();
    if ($current) {
        $existingCount++;
        if ($category && (int) $current->primary_category_id !== (int) $category->id) {
            $exit('Existing source SKU has a different category: '.$item['sku']);
        }
    }
    if (Product::query()->where('slug', $item['slug'])
        ->where('sku', '!=', $item['sku'])->exists()) {
        $exit('Slug collides with an unrelated product: '.$item['slug']);
    }
}

echo 'sinay_catalog_mode='.$argv[2].PHP_EOL;
echo 'sinay_catalog_source=shopier_sinayy'.PHP_EOL;
echo 'sinay_catalog_category='.($category?->name ?? 'to_create').PHP_EOL;
echo 'sinay_catalog_total='.count($rows).PHP_EOL;
echo 'sinay_catalog_existing_by_source_sku='.$existingCount.PHP_EOL;
echo 'sinay_catalog_new_count='.(count($rows)-$existingCount).PHP_EOL;

if ($argv[2] === '--dry-run') exit(0);

$created = 0;
$skipped = 0;
DB::transaction(function () use ($rows, &$created, &$skipped): void {
    $category = Category::query()->where('slug', 'hediyelikler')->lockForUpdate()->first();
    if (!$category) {
        $category = Category::create([
            'parent_id' => null,
            'name' => 'Hediyelikler',
            'slug' => 'hediyelikler',
            'description' => null,
            'image_path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    } elseif (!$category->is_active) {
        $category->update(['is_active' => true]);
    }

    foreach ($rows as $item) {
        $existing = Product::query()->where('sku', $item['sku'])->lockForUpdate()->first();
        if ($existing) {
            if ((int) $existing->primary_category_id !== (int) $category->id) {
                throw new RuntimeException('SKU category conflict.');
            }
            // Never overwrite a manager's later price, photo or stock changes.
            $skipped++;
            continue;
        }
        Product::create([
            'primary_category_id' => $category->id,
            'sku' => $item['sku'],
            'name' => $item['name'],
            'slug' => $item['slug'],
            'description' => null,
            'short_description' => null,
            'price' => $item['price'],
            'compare_at_price' => null,
            'stock_quantity' => 0,
            'tracks_stock' => true,
            'image_path' => null,
            'is_active' => true,
            'is_demo' => false,
            'requires_customer_photos' => false,
        ]);
        $created++;
    }
}, 3);

echo 'sinay_catalog_created='.$created.PHP_EOL;
echo 'sinay_catalog_skipped_existing='.$skipped.PHP_EOL;
echo 'sinay_catalog_category_product_count='.Product::query()
    ->where('primary_category_id', Category::query()->where('slug', 'hediyelikler')->value('id'))
    ->count().PHP_EOL;
echo 'sinay_catalog_source_sku_count='.Product::query()
    ->where('sku','like','SIN-SHOPIER-202611-%')->count().PHP_EOL;
echo 'sinay_catalog_complete=yes'.PHP_EOL;
