<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('gross_cents');
            $table->unsignedTinyInteger('shipping_rate')->nullable();
            $table->text('snapshot_hash');
            $table->longText('amounts');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('invoice_preparations');
    }
};
