<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->json('dimensions_cm')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
        });
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['short_description','weight_kg','dimensions_cm','seo_title','seo_description']);
        });
    }
};
