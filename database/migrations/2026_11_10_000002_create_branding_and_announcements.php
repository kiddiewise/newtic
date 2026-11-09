<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_appearances', function (Blueprint $table) {
            $table->id();
            $table->string('desktop_logo_path')->nullable();
            $table->string('mobile_logo_path')->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('shop_appearances')->insert([
            'id' => 1, 'desktop_logo_path' => null, 'mobile_logo_path' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('shop_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('message', 240);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_announcements');
        Schema::dropIfExists('shop_appearances');
    }
};
