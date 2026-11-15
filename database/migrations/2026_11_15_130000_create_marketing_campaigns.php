<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 255)->unique();
            $table->string('name', 160)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('consent_source', 50)->nullable();
            $table->text('consent_proof')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('iys_reference', 190)->nullable();
            $table->timestamp('iys_confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->char('unsubscribe_token', 64)->unique();
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('banner_path')->nullable();
            $table->string('button_text', 70)->nullable();
            $table->string('button_url', 2000)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaign_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 100)->nullable();
            $table->timestamps();
            $table->unique(['marketing_campaign_id', 'marketing_contact_id'], 'campaign_recipient_unique');
            $table->index(['marketing_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_deliveries');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_contacts');
    }
};
