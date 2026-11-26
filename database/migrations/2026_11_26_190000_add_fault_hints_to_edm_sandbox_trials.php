<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('edm_sandbox_trials',function(Blueprint $table) {
            $table->text('fault_hints')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edm_sandbox_trials',function(Blueprint $table) {
            $table->dropColumn('fault_hints');
        });
    }
};
