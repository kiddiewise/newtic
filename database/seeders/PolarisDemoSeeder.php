<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PolarisDemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach ([
            ['Renkli Surf Kurşun', 'renkli-surf-kursun'],
            ['Fosforlu Kurşun', 'fosforlu-kursun'],
            ['Kaplamasız Kurşun', 'kaplamasiz-kursun'],
            ['Olta Takımları', 'olta-takimlari'],
        ] as $i => [$name, $slug]) {
            $categories[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $i, 'is_active' => true],
            );
        }

        $products = [];
        foreach ([75, 100, 125, 150, 200, 250] as $gram) {
            $product = Product::updateOrCreate(
                ['sku' => 'DEMO-SURF-'.$gram],
                [
                    'primary_category_id' => $categories['renkli-surf-kursun']->id,
                    'name' => 'Renkli Surf Kurşun '.$gram.' gr',
                    'slug' => 'demo-renkli-surf-'.$gram.'-gr',
                    'price' => 59.90 + ($gram / 10),
                    'stock_quantity' => 30, 'is_demo' => true, 'is_active' => true,
                    'image_path' => 'assets/products/demo-sinker.svg',
                ],
            );
            $products[] = $product->id;
        }

        $slider = HomeSection::updateOrCreate(['type' => 'slider', 'sort_order' => 0], [
            'title' => 'Ana slider', 'is_active' => true, 'settings' => ['interval_ms' => 5500],
        ]);
        $slider->slides()->updateOrCreate(['sort_order' => 0], [
            'desktop_image_path' => 'assets/banners/demo-sea.svg',
            'mobile_image_path' => 'assets/banners/demo-sea-mobile.svg',
            'is_active' => true,
        ]);

        $categorySection = HomeSection::updateOrCreate(['type' => 'categories', 'sort_order' => 1], [
            'title' => 'Kategorileri keşfet', 'is_active' => true,
        ]);
        $categorySection->categories()->sync(collect(array_values($categories))
            ->mapWithKeys(fn ($category, $i) => [$category->id => ['sort_order' => $i]])->all());

        $productsSection = HomeSection::updateOrCreate(['type' => 'products', 'sort_order' => 2], [
            'title' => 'Popüler ürünler', 'is_active' => true,
        ]);
        $productsSection->products()->sync(collect($products)
            ->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());

        $banner = HomeSection::updateOrCreate(['type' => 'banner', 'sort_order' => 3], [
            'title' => 'Koleksiyon bannerı', 'is_active' => true,
        ]);
        $banner->slides()->updateOrCreate(['sort_order' => 0], [
            'desktop_image_path' => 'assets/banners/demo-sea.svg',
            'mobile_image_path' => 'assets/banners/demo-sea-mobile.svg',
            'is_active' => true,
        ]);
    }
}
