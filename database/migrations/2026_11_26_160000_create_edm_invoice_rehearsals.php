<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_invoice_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_preparation_id')->unique()->constrained('invoice_preparations')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('profile', 20);
            $table->string('state', 32)->default('offline_reserved');
            $table->char('content_sha256',64);
            $table->char('routing_digest',64);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('edm_invoice_rehearsals');
    }
};
