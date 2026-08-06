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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('assigned_agent_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('mobile', 32);
            $table->string('phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('preferred_contact_method', 16)->default('phone');
            $table->string('intent', 16);
            $table->string('status', 16)->default('active');
            $table->json('preferred_property_types')->nullable();
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('desired_city', 100)->nullable();
            $table->string('desired_district', 100)->nullable();
            $table->decimal('min_area_sqm', 10, 2)->nullable();
            $table->decimal('max_area_sqm', 10, 2)->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->unsignedBigInteger('converted_property_id')->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_customers_agency_id_id');
            $table->index(['agency_id', 'assigned_agent_id', 'status', 'updated_at'], 'idx_customers_agency_agent_status');
            $table->index(['agency_id', 'last_name', 'first_name'], 'idx_customers_agency_name');
            $table->index(['agency_id', 'mobile'], 'idx_customers_agency_mobile');
            $table->index(['agency_id', 'email'], 'idx_customers_agency_email');
            $table->index(['agency_id', 'intent', 'status'], 'idx_customers_agency_intent_status');
            $table->index(['agency_id', 'intent', 'budget_min', 'budget_max'], 'idx_customers_agency_budget');
            $table->index(['agency_id', 'desired_city', 'desired_district'], 'idx_customers_agency_location');
            $table->index(['agency_id', 'deleted_at'], 'idx_customers_agency_deleted');
            $table->foreign('agency_id', 'fk_customers_agency')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign(['agency_id', 'assigned_agent_id'], 'fk_customers_agent')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['agency_id', 'created_by_user_id'], 'fk_customers_creator')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['agency_id', 'converted_property_id'], 'fk_customers_property')
                ->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_contact_method CHECK (preferred_contact_method IN ('phone','email'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_intent CHECK (intent IN ('buy','rent'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','inactive','converted','lost'))");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customers_budget CHECK ((budget_min IS NULL OR budget_min >= 0) AND (budget_max IS NULL OR budget_max >= 0) AND (budget_min IS NULL OR budget_max IS NULL OR budget_min <= budget_max))');
        DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customers_area CHECK ((min_area_sqm IS NULL OR min_area_sqm > 0) AND (max_area_sqm IS NULL OR max_area_sqm > 0) AND (min_area_sqm IS NULL OR max_area_sqm IS NULL OR min_area_sqm <= max_area_sqm))');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_preferred_contact CHECK (preferred_contact_method = 'phone' OR (preferred_contact_method = 'email' AND email IS NOT NULL))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_conversion CHECK ((status = 'converted' AND converted_property_id IS NOT NULL) OR (status <> 'converted' AND converted_property_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
