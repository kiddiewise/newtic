<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_sandbox_trials',function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('account_revision');
            $table->date('issue_date');
            $table->char('xml_sha256',64);
            $table->char('request_sha256',64);
            $table->unsignedInteger('series_warnings');
            $table->unsignedInteger('recipient_warnings');
            $table->string('load_status',32)->default('reserved_unknown');
            $table->string('diagnostic',96)->nullable();
            $table->string('status_query',24)->nullable();
            $table->string('xml_query',24)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Do not silently erase the one-shot reservation on rollback.
        if (Schema::hasTable('edm_sandbox_trials') && \Illuminate\Support\Facades\DB::table('edm_sandbox_trials')->exists()) {
            throw new \RuntimeException('EDM sandbox trial journal is not empty.');
        }
        Schema::dropIfExists('edm_sandbox_trials');
    }
};
