<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('edm_sandbox_trials',function(Blueprint $table) {
            $table->text('fault_hints')->nullable();
            $table->longText('fault_detail_ciphertext')->nullable();
            $table->string('allocated_invoice_id',16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edm_sandbox_trials',function(Blueprint $table) {
            $table->dropColumn(['fault_hints','fault_detail_ciphertext','allocated_invoice_id']);
        });
    }
};
