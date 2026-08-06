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
        Schema::create('saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('user_id');
            $table->string('module', 32);
            $table->string('name', 100);
            $table->json('filters');
            $table->string('sort', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps(6);

            $table->unique(['agency_id', 'id'], 'uq_saved_filters_agency_id_id');
            $table->unique(['agency_id', 'user_id', 'module', 'name'], 'uq_saved_filters_user_module_name');
            $table->index(['agency_id', 'user_id', 'module', 'is_default'], 'idx_saved_filters_user_module_default');
            $table->foreign(['agency_id', 'user_id'], 'fk_saved_filters_user')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE saved_filters ADD CONSTRAINT chk_saved_filters_module CHECK (module IN ('properties','owners','customers'))");
        DB::statement('ALTER TABLE saved_filters ADD CONSTRAINT chk_saved_filters_name CHECK (CHAR_LENGTH(TRIM(name)) BETWEEN 1 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
