<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('edm_order_documents',function(Blueprint $t){
            $t->foreignId('attempt_id')->constrained('edm_submission_attempts')->restrictOnDelete();
            $t->string('format',3);
            $t->uuid('account_revision');
            $t->string('invoice_id',16);
            $t->char('sha256',64);
            $t->unsignedInteger('byte_count');
            $t->boolean('signature_present')->default(false);
            $t->longText('ciphertext');
            $t->timestamp('created_at');
            $t->primary(['attempt_id','format']);
        });
        Schema::create('edm_order_document_checks',function(Blueprint $t){
            $t->foreignId('attempt_id')->constrained('edm_submission_attempts')->restrictOnDelete();
            $t->string('format',3);
            $t->uuid('account_revision');
            $t->string('state',24);
            $t->string('diagnostic',96)->nullable();
            $t->timestamp('checked_at');
            $t->primary(['attempt_id','format']);
        });
        Schema::create('edm_order_send_requests',function(Blueprint $t){
            $t->foreignId('attempt_id')->primary()->constrained('edm_submission_attempts')->restrictOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('account_revision');
            $t->string('invoice_id',16);
            $t->string('state',24);
            $t->string('diagnostic',96)->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['edm_order_documents','edm_order_document_checks','edm_order_send_requests'] as $name)
            if (\Illuminate\Support\Facades\DB::table($name)->exists()) throw new \RuntimeException('Invoice evidence cannot be erased.');
        Schema::dropIfExists('edm_order_send_requests');
        Schema::dropIfExists('edm_order_document_checks');
        Schema::dropIfExists('edm_order_documents');
    }
};
