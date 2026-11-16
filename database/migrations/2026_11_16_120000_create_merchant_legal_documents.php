<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_legal_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('legal_name', 220)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 35)->nullable();
            $table->string('kep_address', 255)->nullable();
            $table->string('mersis_number', 40)->nullable();
            $table->string('tax_office', 130)->nullable();
            $table->string('tax_number', 30)->nullable();
            $table->text('registered_address')->nullable();
            $table->string('support_hours', 160)->nullable();
            $table->boolean('show_paytr_disclosure')->default(false);
            $table->timestamps();
        });

        Schema::create('merchant_legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 180);
            $table->longText('body')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('legal_acknowledgement')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('legal_acknowledgement');
        });
        Schema::dropIfExists('merchant_legal_pages');
        Schema::dropIfExists('merchant_legal_profiles');
    }
};
