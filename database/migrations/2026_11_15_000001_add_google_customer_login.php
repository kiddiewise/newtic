<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('google_login_settings', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_sub', 255)->nullable()->unique();
        });
        Schema::table('shop_appearances', function (Blueprint $table) {
            $table->json('auth_image_paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_appearances', fn (Blueprint $table) => $table->dropColumn('auth_image_paths'));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['google_sub']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('google_sub'));
        Schema::dropIfExists('google_login_settings');
    }
};