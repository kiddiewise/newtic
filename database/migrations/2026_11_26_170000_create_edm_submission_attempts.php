<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_submission_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_preparation_id')->unique()->constrained('invoice_preparations')->restrictOnDelete();
            $table->uuid('uuid')->unique();
            $table->uuid('account_revision');
            $table->string('profile',20);
            $table->char('payload_sha256',64);
            $table->string('state',32)->default('outcome_unknown');
            $table->unsignedBigInteger('lookup_generation')->default(0);
            $table->string('last_lookup_state',32)->nullable();
            $table->string('last_portal_state',32)->nullable();
            $table->char('last_gib_code',4)->nullable();
            $table->string('last_commercial_response',24)->nullable();
            $table->string('last_archive_report',24)->nullable();
            $table->string('last_archive_cancel_report',24)->nullable();
            $table->string('observed_invoice_number',16)->nullable();
            $table->timestamp('last_lookup_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edm_submission_attempts');
    }
};
