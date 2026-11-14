<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('free_shipping_threshold', 12, 2)->default(1000);
            $table->decimal('shipping_fee', 12, 2)->default(79.90);
            $table->timestamps();
        });

        // Safe staging defaults; the admin may change both values.
        DB::table('store_settings')->insert([
            'id' => 1,
            'free_shipping_threshold' => 1000,
            'shipping_fee' => 79.90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
