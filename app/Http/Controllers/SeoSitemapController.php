<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryHierarchy;
use App\Support\SeoMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SeoSitemapController extends Controller
{
    private const PAGE_SIZE = 1000;

    public function robots(Request $request): Response
    {
        // The old physical public/robots.txt is removed. This response follows the active domain.
        $hostNoindex = SeoMetadata::staging($request);
        $text = "User-agent: *\n";
        if ($hostNoindex) {
            $text .= "Disallow: /\n";
        } else {
            $text .= "Disallow: /admin/\nDisallow: /hesabim/\nDisallow: /odeme\n";
            if (SeoMetadata::indexableHost($request)) {
                $text .= "\nSitemap: ".SeoMetadata::url($request, 'sitemap.xml')."\n";
            }
        }
        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function index(Request $request): Response
    {
        $this->publicOnly($request);
        $items = [
            SeoMetadata::url($request, 'sitemap/pages.xml'),
            SeoMetadata::url($request, 'sitemap/categories.xml'),
        ];
        $pages = (int) ceil(Product::query()->where('is_active', true)->where('is_demo', false)->count() / self::PAGE_SIZE);
        for ($i = 1; $i <= $pages; ++$i) {
            $items[] = SeoMetadata::url($request, 'sitemap/products/'.$i.'.xml');
        }
        $xml = $this->begin('sitemapindex', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($items as $url) {
            $xml .= '<sitemap><loc>'.$this->escape($url).'</loc></sitemap>';
        }
        return $this->xml($xml.'</sitemapindex>');
    }

    public function pages(Request $request): Response
    {
        $this->publicOnly($request);
        return $this->xml($this->begin('urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9').
            $this->entry(SeoMetadata::url($request)).'</urlset>');
    }

    public function categories(Request $request): Response
    {
        $this->publicOnly($request);
        $all = CategoryHierarchy::all();
        $xml = $this->begin('urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($all as $category) {
            if (!CategoryHierarchy::visible($category, $all) || $this->categoryNoindex($category, $all)) continue;
            $xml .= $this->entry(
                SeoMetadata::url($request, 'kategori/'.$category->slug),
                $category->updated_at?->toAtomString()
            );
        }

        return $this->xml($xml.'</urlset>');
    }

    public function products(Request $request, int $page): Response
    {
        $this->publicOnly($request);
        abort_if($page < 1 || $page > 50000, 404);
        $all = CategoryHierarchy::all();
        $categories = $all->keyBy('id');
        $products = Product::query()->where('is_active', true)->where('is_demo', false)
            ->orderBy('id')->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get();

        if ($products->isEmpty()) abort(404);

        $xml = $this->begin('urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($products as $product) {
            $category = $categories->get($product->primary_category_id);
            if (!$category || !CategoryHierarchy::visible($category, $all)
                || $this->categoryNoindex($category, $all) || $product->seo_noindex) continue;
            $xml .= $this->entry(
                SeoMetadata::url($request, 'urun/'.$product->slug),
                $product->updated_at?->toAtomString()
            );
        }
        return $this->xml($xml.'</urlset>');
    }

    private function publicOnly(Request $request): void
    {
        abort_unless(SeoMetadata::indexableHost($request), 404);
    }

    private function categoryNoindex(Category $category, $all): bool
    {
        foreach (CategoryHierarchy::ancestors($category, $all) as $ancestor) {
            if ($ancestor->seo_noindex) return true;
        }
        return false;
    }

    private function entry(string $url, ?string $lastmod = null): string
    {
        return '<url><loc>'.$this->escape($url).'</loc>'.
            ($lastmod ? '<lastmod>'.$this->escape($lastmod).'</lastmod>' : '').'</url>';
    }

    private function begin(string $root, string $ns): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<'.$root.' xmlns="'.$ns.'">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xml(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}