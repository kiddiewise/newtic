<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_archive_order_metadata',function(Blueprint $table){
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('account_revision');
            $table->string('website',255);
            $table->string('series',3);
            $table->unsignedSmallInteger('series_year');
            $table->string('buyer_classification',32);
            $table->char('source_hash',64);
            $table->longText('metadata_ciphertext');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('edm_archive_order_metadata')&&\Illuminate\Support\Facades\DB::table('edm_archive_order_metadata')->exists()) {
            throw new \RuntimeException('EDM archive order metadata is not empty.');
        }
        Schema::dropIfExists('edm_archive_order_metadata');
    }
};
