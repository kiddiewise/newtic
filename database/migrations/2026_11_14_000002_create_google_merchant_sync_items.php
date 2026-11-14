<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_merchant_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('merchant_id', 32);
            $table->string('offer_id', 80);
            $table->string('payload_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'offer_id'], 'gmerchant_account_offer_unique');
            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_merchant_sync_items');
    }
};