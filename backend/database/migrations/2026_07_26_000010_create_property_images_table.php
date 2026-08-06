<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('storage_path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_property_images_agency_id_id');
            $table->unique('storage_path', 'uq_property_images_storage_path');
            $table->index(['agency_id', 'property_id', 'deleted_at', 'sort_order'], 'idx_property_images_property_order');
            $table->index(['agency_id', 'property_id', 'deleted_at', 'is_cover'], 'idx_property_images_property_cover');
            $table->foreign(['agency_id', 'property_id'], 'fk_property_images_property')
                ->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
            $table->foreign(['agency_id', 'uploaded_by_user_id'], 'fk_property_images_uploader')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE property_images ADD CONSTRAINT chk_property_images_mime CHECK (mime_type IN ('image/jpeg','image/png','image/webp'))");
        DB::statement('ALTER TABLE property_images ADD CONSTRAINT chk_property_images_size CHECK (size_bytes BETWEEN 1 AND 10485760)');
        DB::statement('ALTER TABLE property_images ADD CONSTRAINT chk_property_images_dimensions CHECK (width > 0 AND height > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
