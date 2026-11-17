<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_customer_photos')->default(false);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('customer_photos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('customer_photos'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('requires_customer_photos'));
    }
};
