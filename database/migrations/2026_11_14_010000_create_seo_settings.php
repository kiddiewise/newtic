<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 120)->default('Polaris Kurşun');
            $table->string('title_suffix', 100)->default('Polaris Kurşun');
            $table->string('home_title', 190)->nullable();
            $table->string('home_description', 500)->nullable();
            $table->string('default_description', 500)->nullable();
            $table->string('social_image_path', 255)->nullable();
            $table->string('google_verification', 190)->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('nofollow')->default(false);
            $table->json('page_metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('seo_title', 190)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('seo_noindex')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('seo_noindex')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('seo_noindex'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['seo_title', 'seo_description', 'seo_noindex']));
        Schema::dropIfExists('seo_settings');
    }
};