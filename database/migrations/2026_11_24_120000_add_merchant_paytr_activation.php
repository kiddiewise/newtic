<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paytr_settings', function (Blueprint $table) {
            $table->boolean('admin_managed')->default(false);
            $table->boolean('installments_enabled')->default(false);
            $table->unsignedTinyInteger('max_installment')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('paytr_settings', function (Blueprint $table) {
            $table->dropColumn(['admin_managed', 'installments_enabled', 'max_installment']);
        });
    }
};
