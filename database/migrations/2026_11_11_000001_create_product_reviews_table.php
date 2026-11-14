<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('status', 16)->default('pending')->index();
            $table->boolean('is_test')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status', 'is_test']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
