<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edm_submission_attempts', function (Blueprint $table) {
            $table->string('last_portal_token',64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edm_submission_attempts', function (Blueprint $table) {
            $table->dropColumn('last_portal_token');
        });
    }
};
