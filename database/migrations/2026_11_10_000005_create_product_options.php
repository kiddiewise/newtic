<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('value', 60);
            $table->timestamps();
            $table->unique(['product_id', 'value']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('option_label', 80)->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            // Immutable snapshot; order history survives option editing/removal.
            $table->string('option_label', 80)->nullable();
            $table->string('option_value', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['option_label', 'option_value']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('option_label'));
        Schema::dropIfExists('product_options');
    }
};
