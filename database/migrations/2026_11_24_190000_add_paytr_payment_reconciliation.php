<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paytr_payment_attempts', function (Blueprint $table) {
            // Legacy rows cannot prove that a token was never issued.
            $table->boolean('issuance_tracked')->default(false);
            $table->timestamp('token_request_started_at')->nullable();
            $table->timestamp('reconciliation_checked_at')->nullable();
            $table->string('reconciliation_result', 32)->nullable();
            $table->index(['status','reconciliation_checked_at'], 'paytr_pending_reconcile_idx');
        });
    }

    public function down(): void
    {
        Schema::table('paytr_payment_attempts', function (Blueprint $table) {
            $table->dropIndex('paytr_pending_reconcile_idx');
            $table->dropColumn(['issuance_tracked','token_request_started_at','reconciliation_checked_at','reconciliation_result']);
        });
    }
};
