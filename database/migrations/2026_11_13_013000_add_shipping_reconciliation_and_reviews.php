<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipping_shipments', function (Blueprint $table) {
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_sync_failed_at')->nullable();
            $table->unsignedSmallInteger('sync_failure_count')->default(0);
            $table->timestamp('exception_acknowledged_at')->nullable();
            $table->string('exception_acknowledged_status', 50)->nullable();
            $table->foreignId('exception_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('exception_note', 500)->nullable();
            $table->index(['state', 'last_synced_at']);
            $table->index(['remote_status', 'exception_acknowledged_at'], 'shipments_exception_attention');
        });
        Schema::create('shipping_exception_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remote_status', 50)->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipping_shipment_id', 'created_at'], 'shipping_reviews_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_exception_reviews');
        Schema::table('shipping_shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_exception_attention');
            $table->dropIndex(['state', 'last_synced_at']);
            $table->dropConstrainedForeignId('exception_acknowledged_by');
            $table->dropColumn([
                'last_sync_attempt_at','last_synced_at','last_sync_failed_at','sync_failure_count',
                'exception_acknowledged_at','exception_acknowledged_status','exception_note',
            ]);
        });
    }
};
