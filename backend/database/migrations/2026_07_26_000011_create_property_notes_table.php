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
        Schema::create('property_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('author_user_id');
            $table->text('body');
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_property_notes_agency_id_id');
            $table->index(['agency_id', 'property_id', 'deleted_at', 'created_at'], 'idx_property_notes_property_created');
            $table->index(['agency_id', 'author_user_id', 'created_at'], 'idx_property_notes_author');
            $table->foreign(['agency_id', 'property_id'], 'fk_property_notes_property')
                ->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
            $table->foreign(['agency_id', 'author_user_id'], 'fk_property_notes_author')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE property_notes ADD CONSTRAINT chk_property_notes_body CHECK (CHAR_LENGTH(TRIM(body)) BETWEEN 1 AND 5000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_notes');
    }
};
