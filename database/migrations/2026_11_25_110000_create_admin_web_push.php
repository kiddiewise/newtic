<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_push_keys', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('private_key');
            $table->string('public_key',100);
        });
        Schema::create('admin_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash',64)->unique();
            $table->text('endpoint');
            $table->char('session_hash',64)->index();
            $table->unsignedBigInteger('last_order_id')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('retry_at')->nullable()->index();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('admin_push_subscriptions');
        Schema::dropIfExists('admin_push_keys');
    }
};
