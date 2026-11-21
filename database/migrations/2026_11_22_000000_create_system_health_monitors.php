<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_health_monitors', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('status', 12);
            // Intentionally only fixed technical codes: never store API payloads,
            // exception bodies, credentials, email addresses, or customer data.
            $table->string('detail', 48)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_monitors');
    }
};
