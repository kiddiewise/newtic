<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_scheme', 8)->default('smtp');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->text('admin_emails')->nullable();
            $table->string('test_recipient')->nullable();
            $table->unsignedTinyInteger('review_delay_days')->default(3);
            $table->json('events')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('dedupe_key', 64)->unique();
            $table->string('event', 60)->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->text('secret_payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 120)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_settings');
    }
};
