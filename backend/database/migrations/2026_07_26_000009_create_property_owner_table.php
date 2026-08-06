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
        Schema::create('property_owner', function (Blueprint $table): void {
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('owner_id');
            $table->decimal('ownership_percentage', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps(6);

            $table->primary(['agency_id', 'property_id', 'owner_id'], 'pk_property_owner');
            $table->index(['agency_id', 'owner_id', 'property_id'], 'idx_property_owner_owner');
            $table->index(['agency_id', 'property_id', 'is_primary'], 'idx_property_owner_primary');
            $table->foreign(['agency_id', 'property_id'], 'fk_property_owner_property')
                ->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
            $table->foreign(['agency_id', 'owner_id'], 'fk_property_owner_owner')
                ->references(['agency_id', 'id'])->on('owners')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE property_owner ADD CONSTRAINT chk_property_owner_share CHECK (ownership_percentage IS NULL OR ownership_percentage BETWEEN 0.01 AND 100.00)');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owner');
    }
};
