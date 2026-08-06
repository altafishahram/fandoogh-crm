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
        Schema::create('property_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('changed_by_user_id');
            $table->string('action', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->dateTime('occurred_at', 6)->useCurrent();

            $table->unique(['agency_id', 'id'], 'uq_property_histories_agency_id_id');
            $table->index(['agency_id', 'property_id', 'occurred_at', 'id'], 'idx_property_histories_property_time');
            $table->index(['agency_id', 'changed_by_user_id', 'occurred_at'], 'idx_property_histories_actor_time');
            $table->index(['agency_id', 'action', 'occurred_at'], 'idx_property_histories_action_time');
            $table->foreign(['agency_id', 'property_id'], 'fk_property_histories_property')
                ->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
            $table->foreign(['agency_id', 'changed_by_user_id'], 'fk_property_histories_actor')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE property_histories ADD CONSTRAINT chk_property_histories_action CHECK (action IN ('created','updated','status_changed','archived','unarchived','deleted','restored','owners_changed','assignment_changed','images_changed'))");
        DB::statement("ALTER TABLE property_histories ADD CONSTRAINT chk_property_histories_from CHECK (from_status IS NULL OR from_status IN ('available','reserved','sold','rented','archived'))");
        DB::statement("ALTER TABLE property_histories ADD CONSTRAINT chk_property_histories_to CHECK (to_status IS NULL OR to_status IN ('available','reserved','sold','rented','archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('property_histories');
    }
};
