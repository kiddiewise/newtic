<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edm_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->uuid('revision')->nullable();
            $table->string('probe_status', 24)->nullable();
            $table->integer('counter_left')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edm_settings');
    }
};
