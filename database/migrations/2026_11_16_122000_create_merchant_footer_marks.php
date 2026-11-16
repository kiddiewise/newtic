<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_footer_marks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('title', 100);
            $table->string('image_path');
            $table->string('verification_url', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('use_authorized')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_footer_marks');
    }
};
