<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_return_requests', function (Blueprint $table) {
            $table->timestamp('refund_checked_at')->nullable()->index();
        });
    }
    public function down(): void
    {
        Schema::table('order_return_requests', function (Blueprint $table) {
            $table->dropIndex(['refund_checked_at']);
            $table->dropColumn('refund_checked_at');
        });
    }
};
