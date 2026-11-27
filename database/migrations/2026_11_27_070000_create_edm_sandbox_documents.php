<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_sandbox_documents',function(Blueprint $table) {
            $table->uuid('uuid');
            $table->string('format',3);
            $table->uuid('account_revision');
            $table->string('invoice_id',16);
            $table->char('sha256',64);
            $table->unsignedInteger('byte_count');
            $table->longText('ciphertext');
            $table->timestamp('created_at');
            $table->primary(['uuid','format']);
        });
        Schema::table('edm_sandbox_trials',function(Blueprint $table) {
            $table->string('pdf_query',24)->nullable();
            $table->string('first_status_diagnostic',96)->nullable();
            $table->string('first_xml_diagnostic',96)->nullable();
            $table->char('xslt_sha256',64)->nullable();
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('edm_sandbox_documents')->exists() ||
            \Illuminate\Support\Facades\DB::table('edm_sandbox_trials')->whereNotNull('xslt_sha256')->exists()) {
            throw new \RuntimeException('EDM numbered sandbox evidence cannot be erased by rollback.');
        }
        Schema::dropIfExists('edm_sandbox_documents');
        Schema::table('edm_sandbox_trials',fn(Blueprint $table)=>$table->dropColumn(['pdf_query','first_status_diagnostic','first_xml_diagnostic','xslt_sha256']));
    }
};
