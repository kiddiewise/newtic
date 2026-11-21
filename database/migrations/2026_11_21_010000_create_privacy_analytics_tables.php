<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            // HMAC of a browser-generated random UUID, never IP or user ID.
            $table->string('session_key', 64)->unique();
            $table->string('current_path', 220);
            $table->string('page_type', 24)->default('other');
            $table->string('device', 16)->default('desktop');
            $table->string('source', 100)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->index();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64);
            $table->string('event_name', 32);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('page_path', 220);
            // Search phrases are filtered to avoid obvious PII.
            $table->string('search_term', 100)->nullable();
            $table->unsignedInteger('results_count')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('device', 16)->default('desktop');
            $table->timestamp('occurred_at');
            $table->index(['event_name', 'occurred_at']);
            $table->index(['product_id', 'event_name', 'occurred_at'], 'analytics_product_event_date_idx');
            $table->index(['session_key', 'occurred_at']);
            $table->index(['occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sessions');
    }
};
