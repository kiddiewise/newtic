<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Never rewrite historical order states; shipping info is optional.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier_name', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('tracking_url', 2000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) =>
            $table->dropColumn(['carrier_name', 'tracking_number', 'tracking_url'])
        );
    }
};
