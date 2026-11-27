<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('edm_settings',function(Blueprint $t){
            $t->boolean('auto_invoice')->default(false);
            $t->timestamp('auto_invoice_since')->nullable();
            $t->unsignedTinyInteger('shipping_vat_rate')->nullable();
        });
        Schema::create('invoice_dispatch_requests',function(Blueprint $t){
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('account_revision');
            $t->unsignedTinyInteger('shipping_rate')->nullable();
            $t->string('source',12);
            $t->string('state',24)->default('queued')->index();
            $t->string('diagnostic',100)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('invoice_dispatch_requests')->exists()) throw new \RuntimeException('Invoice requests are durable.');
        Schema::dropIfExists('invoice_dispatch_requests');
        Schema::table('edm_settings',fn(Blueprint $t)=>$t->dropColumn(['auto_invoice','auto_invoice_since','shipping_vat_rate']));
    }
};
