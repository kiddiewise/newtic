<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('paytr_settings', function (Blueprint $table) {
            $table->boolean('admin_managed')->default(false);
            $table->boolean('installments_enabled')->default(false);
            $table->unsignedTinyInteger('max_installment')->default(0);
        });
        // Preserve explicitly enabled legacy sandbox stores, never turn on
        // production payments and never activate a freshly installed store.
        if (config('newtic.checkout_test_mode', true)) {
            DB::table('paytr_settings')->where('is_enabled', true)->where('test_mode', true)
                ->update(['admin_managed' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('paytr_settings', function (Blueprint $table) {
            $table->dropColumn(['admin_managed', 'installments_enabled', 'max_installment']);
        });
    }
};
