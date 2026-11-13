<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('color', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('badge_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_badge_id')->constrained('product_badges')->cascadeOnDelete();
            $table->primary(['product_id', 'product_badge_id']);
            $table->index('product_badge_id');
        });

        // Reusable examples only. No products are tagged automatically.
        $defaults = [
            ['Popüler Ürün', 'lilac', 10],
            ['Hızlı Teslimat', 'mint', 20],
            ['Yeni Ürün', 'sky', 30],
            ['Çok Satan', 'rose', 40],
            ['Öne Çıkan', 'lemon', 50],
            ['Özel Seçim', 'peach', 60],
            ['Kampanyalı', 'aqua', 70],
            ['Sınırlı Stok', 'pink', 80],
            ['Editörün Seçimi', 'slate', 90],
        ];

        DB::table('product_badges')->insert(array_map(
            fn (array $entry) => [
                'name' => $entry[0],
                'color' => $entry[1],
                'sort_order' => $entry[2],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $defaults
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_product');
        Schema::dropIfExists('product_badges');
    }
};
