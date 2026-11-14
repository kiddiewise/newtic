<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('full_name', 160);
            $table->string('phone', 20);
            $table->string('city', 70);
            $table->string('district', 100);
            $table->text('street_address');
            $table->string('invoice_type', 12)->default('individual');
            $table->string('company_title', 200)->nullable();
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 11)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 34)->unique();
            $table->uuid('checkout_token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 160);
            $table->string('customer_email', 255);
            $table->string('phone', 20);
            $table->string('address_label', 50)->nullable();
            $table->string('city', 70);
            $table->string('district', 100);
            $table->text('street_address');
            $table->string('invoice_type', 12)->default('individual');
            $table->string('company_title', 200)->nullable();
            $table->string('tax_office', 100)->nullable();
            $table->string('tax_number', 11)->nullable();
            $table->text('order_note')->nullable();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('status', 24)->default('pending_payment')->index();
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('shipping_cents');
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('TRY');
            $table->boolean('is_test')->default(true);
            $table->timestamp('placed_at');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 190);
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customer_addresses');
    }
};
