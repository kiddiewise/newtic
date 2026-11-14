<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paytr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_id', 64)->nullable();
            // Values are encrypted by Laravel's encrypted cast before storage.
            // LONGTEXT also accommodates encrypted payload overhead.
            $table->longText('merchant_key')->nullable();
            $table->longText('merchant_salt')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('test_mode')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paytr_settings');
    }
};
