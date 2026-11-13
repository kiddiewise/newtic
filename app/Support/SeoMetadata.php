<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\SeoSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SeoMetadata
{
    public static function staging(Request $request): bool
    {
        $host = strtolower($request->getHost());
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.hostingersite.com')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }

    public static function indexableHost(Request $request): bool
    {
        return !self::staging($request) && !SeoSetting::current()->noindex;
    }

    public static function url(Request $request, string $path = '/'): string
    {
        // The active request host is authoritative, not an old staging APP_URL.
        // This also keeps the canonical and sitemap domain portable after migration.
        return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
    }

    public static function image(Request $request, ?string $path): ?string
    {
        if (!$path || !preg_match('~^(?:storage|assets)/[A-Za-z0-9_\-./]+\.(?:png|jpe?g|webp|avif|svg)$~iD', $path)
            || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return self::url($request, $path);
    }

    /** @return array{title:string,description:string,robots:string,canonical:?string,image:?string,og_type:string,verification:?string,structured:array} */
    public static function forRequest(Request $request): array
    {
        $setting = SeoSetting::current();
        $route = (string) ($request->route()?->getName() ?? '');
        $title = (string) ($setting->site_name ?: 'Polaris Kurşun');
        $suffix = trim((string) ($setting->title_suffix ?: $title));
        $description = (string) ($setting->default_description ?: 'Polaris Kurşun balıkçılık ekipmanları, kurşun ve olta malzemeleri.');
        $image = self::image($request, $setting->social_image_path);
        $canonical = null;
        $structured = [];
        $individualNoindex = false;
        $ogType = 'website';

        if ($route === 'home') {
            $title = trim((string) ($setting->home_title ?: $title.' | Balıkçılık Ekipmanları'));
            $description = trim((string) ($setting->home_description ?: $description));
            $canonical = self::url($request);
            $org = [
                '@type' => 'Organization',
                '@id' => $canonical.'#organization',
                'name' => $setting->site_name ?: 'Polaris Kurşun',
                'url' => $canonical,
            ];
            if ($image) $org['logo'] = $image;
            $structured = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $org,
                    [
                        '@type' => 'WebSite',
                        '@id' => $canonical.'#website',
                        'name' => $setting->site_name ?: 'Polaris Kurşun',
                        'url' => $canonical,
                        'inLanguage' => 'tr-TR',
                    ],
                ],
            ];
        } elseif ($route === 'category.show') {
            $category = Category::query()->where('slug', (string) $request->route('slug'))->first();
            if ($category && CategoryHierarchy::visible($category)) {
                $title = trim((string) ($category->seo_title ?: $category->name.' | '.$suffix));
                $description = trim((string) ($category->seo_description ?: $category->description ?: $category->name.' ürün çeşitleri, gramaj seçenekleri ve güncel fiyatları.'));
                $image = self::image($request, $category->image_path) ?: $image;
                $canonical = self::url($request, 'kategori/'.$category->slug);
                $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
                if ($page && $page > 1) $canonical .= '?page='.$page;
                $individualNoindex = (bool) $category->seo_noindex;
                $chain = CategoryHierarchy::ancestors($category);
                $structured = self::breadcrumb($request, $chain);
            }
        } elseif ($route === 'product.show') {
            $product = ProductReviewVisibility::withRatingSummary(
                Product::query()->with(['category', 'images'])
            )->where('slug', (string) $request->route('slug'))->first();
            if ($product && $product->is_active && $product->category && CategoryHierarchy::visible($product->category)) {
                $title = trim((string) ($product->seo_title ?: $product->name.' | '.$suffix));
                $description = trim((string) ($product->seo_description ?: $product->short_description ?: $product->description ?: $product->name));
                $image = self::image($request, $product->image_path) ?: $image;
                $canonical = self::url($request, 'urun/'.$product->slug);
                $individualNoindex = (bool) $product->seo_noindex || (bool) $product->category->seo_noindex;
                foreach (CategoryHierarchy::ancestors($product->category) as $ancestor) {
                    if ($ancestor->seo_noindex) $individualNoindex = true;
                }
                $ogType = 'product';
                $breadcrumbs = self::breadcrumb($request, CategoryHierarchy::ancestors($product->category));
                $structured = $breadcrumbs;
                if ($product->image_path) {
                    $photos = array_values(array_unique(array_filter(array_merge(
                        [$image],
                        $product->images->pluck('path')->map(fn ($path) => self::image($request, $path))->all()
                    ))));
                    $productNode = [
                        '@context' => 'https://schema.org',
                        '@type' => 'Product',
                        '@id' => $canonical.'#product',
                        'url' => $canonical,
                        'name' => $product->name,
                        'description' => Str::limit(trim(strip_tags($description)), 5000, ''),
                        'sku' => $product->sku,
                        'brand' => [
                            '@type' => 'Brand',
                            'name' => $product->google_brand ?: 'Polaris Kurşun',
                        ],
                        'image' => array_slice($photos, 0, 10),
                        'offers' => [
                            '@type' => 'Offer',
                            'url' => $canonical,
                            'priceCurrency' => 'TRY',
                            'price' => number_format((float) $product->price, 2, '.', ''),
                            'priceValidUntil' => null,
                            'availability' => $product->tracks_stock && $product->stock_quantity <= 0
                                ? 'https://schema.org/OutOfStock'
                                : 'https://schema.org/InStock',
                            'itemCondition' => 'https://schema.org/NewCondition',
                            'seller' => [
                                '@type' => 'Organization',
                                'name' => $setting->site_name ?: 'Polaris Kurşun',
                            ],
                        ],
                    ];
                    unset($productNode['offers']['priceValidUntil']);
                    if ($product->gtin) $productNode['gtin'] = $product->gtin;
                    if ($product->mpn) $productNode['mpn'] = $product->mpn;
                    // Only approved, non-test purchase reviews, identical to public review list.
                    if ((int) ($product->review_count ?? 0) > 0 && (float) $product->review_average > 0) {
                        $productNode['aggregateRating'] = [
                            '@type' => 'AggregateRating',
                            'ratingValue' => round((float) $product->review_average, 2),
                            'ratingCount' => (int) $product->review_count,
                            'bestRating' => 5,
                            'worstRating' => 1,
                        ];
                    }
                    $structured = [
                        '@context' => 'https://schema.org',
                        '@graph' => [$productNode, $breadcrumbs],
                    ];
                }
            }
        } else {
            // Checkout, account, search, cart and other utility pages must
            // never enter Google even when the public catalogue is indexable.
            $individualNoindex = true;
            $pageKey = array_search($route, SeoSetting::PAGE_ROUTES, true);
            $pageMeta = (array) (($setting->page_metadata ?? [])[$pageKey ?: ''] ?? []);
            $title = trim((string) ($pageMeta['title'] ?? '')) ?: $title;
            $description = trim((string) ($pageMeta['description'] ?? '')) ?: $description;
        }

        $description = Str::limit(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? '', 500, '');
        $forced = self::staging($request) || (bool) $setting->noindex || $individualNoindex;
        $robots = $forced ? 'noindex,nofollow,noarchive' : ($setting->nofollow ? 'index,nofollow' : 'index,follow');

        return compact('title', 'description', 'robots', 'canonical', 'image', 'structured')
            + ['og_type' => $ogType, 'verification' => $setting->google_verification];
    }

    private static function breadcrumb(Request $request, array $categories): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Ana sayfa',
                'item' => self::url($request),
            ],
        ];
        foreach ($categories as $category) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name' => $category->name,
                'item' => self::url($request, 'kategori/'.$category->slug),
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}