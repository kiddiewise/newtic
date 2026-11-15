<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newtic_installations', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('version', 30);
            $table->string('domain');
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newtic_installations');
    }
};
