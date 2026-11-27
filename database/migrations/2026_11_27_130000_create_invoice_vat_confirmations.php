<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_vat_confirmations',function(Blueprint $t){
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedTinyInteger('vat_rate');
            $t->timestamp('created_at');
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('invoice_vat_confirmations')->exists()) throw new \RuntimeException('Confirmed VAT history cannot be erased.');
        Schema::dropIfExists('invoice_vat_confirmations');
    }
};
