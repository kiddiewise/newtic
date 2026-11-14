<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_merchant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('merchant_id', 32)->nullable();
            $table->string('data_source_name', 140)->nullable();
            $table->boolean('auto_sync')->default(false);
            $table->string('sync_status', 24)->default('idle');
            $table->unsignedBigInteger('sync_cursor')->default(0);
            $table->unsignedInteger('sync_sent')->default(0);
            $table->unsignedInteger('sync_unchanged')->default(0);
            $table->unsignedInteger('sync_removed')->default(0);
            $table->unsignedInteger('sync_errors')->default(0);
            $table->unsignedInteger('sync_total')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_merchant_settings');
    }
};