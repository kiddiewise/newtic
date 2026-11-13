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
            $table->string('footer_text', 120)->default('POLARIS KURŞUN • TÜRKİYE');
            $table->timestamps();
        });

        // Carry over the links already displayed by the existing storefront.
        DB::table('mega_menu_settings')->insert([
            'id' => 1,
            'instagram_url' => 'https://instagram.com/polariskursun',
            'youtube_url' => 'https://youtube.com/@polariskursun',
            'facebook_url' => 'https://facebook.com/polariskursun',
            'x_url' => null,
            'whatsapp_number' => '905462629002',
            'whatsapp_message' => null,
            'footer_text' => 'POLARIS KURŞUN • TÜRKİYE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_menu_settings');
    }
};
