<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->string('desktop_image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('home_section_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['home_section_id', 'category_id']);
        });

        Schema::create('home_section_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['home_section_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_products');
        Schema::dropIfExists('home_section_categories');
        Schema::dropIfExists('home_slides');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
