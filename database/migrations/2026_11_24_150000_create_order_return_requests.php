<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paytr_settings', function (Blueprint $table) {
            $table->boolean('live_refunds_enabled')->default(false);
        });
        Schema::create('order_return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('kind', 16);
            $table->string('status', 24)->default('requested')->index();
            $table->string('reason', 32);
            $table->text('customer_note')->nullable();
            $table->json('items');
            $table->unsignedBigInteger('items_cents');
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('refund_cents')->default(0);
            $table->string('refund_status', 24)->default('none');
            $table->string('return_carrier', 100)->nullable();
            $table->string('return_code', 100)->nullable();
            $table->text('public_note')->nullable();
            $table->string('bank_reference', 100)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
        Schema::create('order_return_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_events');
        Schema::dropIfExists('order_return_requests');
        Schema::table('paytr_settings', fn (Blueprint $table) => $table->dropColumn('live_refunds_enabled'));
    }
};
