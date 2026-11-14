<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique();
            $table->longText('api_key')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('remote_id');
            $table->string('name', 190);
            $table->string('carrier_code', 90)->nullable();
            $table->boolean('is_dummy')->default(false);
            $table->timestamps();
            $table->unique(['shipping_provider_id', 'remote_id']);
        });
        Schema::create('shipping_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('remote_id');
            $table->string('name', 190);
            $table->timestamps();
            $table->unique(['shipping_provider_id', 'remote_id']);
        });
        Schema::create('shipping_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('remote_id')->nullable();
            $table->string('state', 24)->default('creating'); // creating, submitted, uncertain
            $table->string('remote_status', 50)->nullable();
            $table->string('barcode', 255)->nullable();
            $table->string('tracking_number', 255)->nullable();
            $table->string('tracking_link', 2000)->nullable();
            $table->decimal('desi', 8, 2);
            $table->decimal('kg', 8, 2)->nullable();
            $table->string('last_callback_time', 19)->nullable();
            $table->timestamps();
            $table->unique(['shipping_provider_id', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_shipments');
        Schema::dropIfExists('shipping_warehouses');
        Schema::dropIfExists('shipping_carriers');
        Schema::dropIfExists('shipping_providers');
    }
};
