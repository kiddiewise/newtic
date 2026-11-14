<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paytr_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('merchant_oid', 64)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('TRY');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_test')->default(true);
            $table->string('failed_reason_code', 12)->nullable();
            $table->text('failed_reason_message')->nullable();
            $table->unsignedBigInteger('paid_total_cents')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paytr_payment_attempts');
    }
};
