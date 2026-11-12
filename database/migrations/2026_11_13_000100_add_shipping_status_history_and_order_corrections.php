<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('source', 20);
            $table->string('provider_time', 19)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipping_shipment_id', 'created_at']);
        });

        Schema::create('order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->string('source', 30);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        // Preserve the current state of shipments created before this release.
        // Old intermediate transitions cannot be reconstructed from one snapshot.
        DB::table('shipping_shipments')->whereNotNull('remote_status')
            ->where('remote_status', '<>', '')
            ->orderBy('id')->chunkById(200, function ($shipments): void {
                foreach ($shipments as $shipment) {
                    DB::table('shipping_status_events')->insert([
                        'shipping_shipment_id' => $shipment->id,
                        'status' => $shipment->remote_status,
                        'source' => 'initial_snapshot',
                        'created_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_changes');
        Schema::dropIfExists('shipping_status_events');
    }
};
