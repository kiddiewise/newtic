<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mega_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_image_path')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('whatsapp_number', 25)->nullable();
            $table->string('whatsapp_message', 500)->nullable();
            $table->string('footer_text', 120)->default('');
            $table->timestamps();
        });

        // A fresh store must never inherit another merchant's contact information.
        // Existing Polaris installations keep their stored row unchanged.
        DB::table('mega_menu_settings')->insert([
            'id' => 1,
            'instagram_url' => null,
            'youtube_url' => null,
            'facebook_url' => null,
            'x_url' => null,
            'whatsapp_number' => null,
            'whatsapp_message' => null,
            'footer_text' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_menu_settings');
    }
};
