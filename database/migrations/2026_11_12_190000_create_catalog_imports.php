<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_site', 190)->default('polariskursun.com');
            $table->string('original_filename', 190);
            $table->string('file_path', 255)->nullable();
            $table->string('status', 20)->default('preview')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->boolean('publish_products')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_import_id')->constrained('catalog_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('woo_id', 80)->nullable();
            $table->string('row_type', 30)->default('simple');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('payload');
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['catalog_import_id', 'row_number']);
            $table->index(['catalog_import_id', 'status', 'id']);
            $table->index(['catalog_import_id', 'woo_id']);
        });

        Schema::create('catalog_import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('source_site', 190);
            $table->string('source_key', 190);
            $table->string('source_permalink', 1000)->nullable();
            $table->json('original_data')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();
            $table->unique(['source_site', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_sources');
        Schema::dropIfExists('catalog_import_rows');
        Schema::dropIfExists('catalog_imports');
    }
};
