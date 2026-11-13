<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\GoogleMerchantProduct;
use App\Support\CategoryHierarchy;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GoogleMerchantFeedController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->stream(function (): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel>';
            $this->tag('title', 'Polaris Kurşun Ürün Kataloğu');
            $this->tag('link', url('/'));
            $this->tag('description', 'NEWTIC ürün kataloğu - Türkiye');

            $allCategories = CategoryHierarchy::all();
            Product::query()->with(['category', 'images'])
                ->where('is_active', true)->where('is_demo', false)
                ->orderBy('id')->chunkById(100, function ($products) use ($allCategories): void {
                    foreach ($products as $product) {
                        if (!GoogleMerchantProduct::eligible($product, $allCategories)) {
                            continue;
                        }
                        $attr = GoogleMerchantProduct::attributes($product);
                        echo '<item>';
                        $this->tag('g:id', GoogleMerchantProduct::offerId($product));
                        $this->tag('title', $attr['title']);
                        $this->tag('description', $attr['description']);
                        $this->tag('link', $attr['link']);
                        $this->tag('g:image_link', $attr['imageLink']);
                        foreach ($attr['additionalImageLinks'] ?? [] as $image) {
                            $this->tag('g:additional_image_link', $image);
                        }
                        $this->tag('g:availability', $attr['availability'] === 'IN_STOCK' ? 'in_stock' : 'out_of_stock');
                        $this->tag('g:condition', 'new');
                        $this->tag('g:brand', $attr['brand']);
                        $this->tag('g:price', number_format((float) ($product->compare_at_price && $product->compare_at_price > $product->price ? $product->compare_at_price : $product->price), 2, '.', '').' TRY');
                        if (isset($attr['salePrice'])) {
                            $this->tag('g:sale_price', number_format((float) $product->price, 2, '.', '').' TRY');
                        }
                        if ($product->gtin) $this->tag('g:gtin', $product->gtin);
                        if ($product->mpn) $this->tag('g:mpn', $product->mpn);
                        $this->tag('g:product_type', $product->category?->name ?? 'Ürün');
                        echo '</item>';
                    }
                });
            echo '</channel></rss>';
        }, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tag(string $name, string $value): void
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
        echo '<'.$name.'>'.htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$name.'>';
    }
}