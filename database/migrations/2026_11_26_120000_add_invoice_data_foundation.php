<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_rate')->default(20);
        });
        Schema::table('order_items', function (Blueprint $table) {
            // Historical orders cannot be assigned an assumed tax rate.
            $table->unsignedTinyInteger('vat_rate')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->text('billing_tckn')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('billing_tckn'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('vat_rate'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('vat_rate'));
    }
};
