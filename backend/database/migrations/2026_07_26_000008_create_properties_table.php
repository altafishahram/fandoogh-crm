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
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->string('code', 32);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('property_type', 32);
            $table->string('transaction_type', 16);
            $table->string('status', 16)->default('available');
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->char('currency_code', 3);
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->decimal('monthly_rent', 15, 2)->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->smallInteger('floor_number')->nullable();
            $table->unsignedSmallInteger('total_floors')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->unsignedTinyInteger('parking_spaces')->default(0);
            $table->boolean('has_storage_room')->default(false);
            $table->boolean('has_elevator')->default(false);
            $table->boolean('has_balcony')->default(false);
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('street_address', 500);
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('available_from')->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->dateTime('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_properties_agency_id_id');
            $table->unique(['agency_id', 'code'], 'uq_properties_agency_code');
            $table->index(['agency_id', 'status', 'updated_at', 'id'], 'idx_properties_agency_status_updated');
            $table->index(['agency_id', 'property_type', 'transaction_type'], 'idx_properties_agency_type_transaction');
            $table->index(['agency_id', 'assigned_agent_id', 'status'], 'idx_properties_agency_agent_status');
            $table->index(['agency_id', 'city', 'district'], 'idx_properties_agency_city_district');
            $table->index(['agency_id', 'transaction_type', 'sale_price'], 'idx_properties_agency_sale_price');
            $table->index(['agency_id', 'transaction_type', 'monthly_rent'], 'idx_properties_agency_monthly_rent');
            $table->index(['agency_id', 'area_sqm'], 'idx_properties_agency_area');
            $table->index(['agency_id', 'deleted_at'], 'idx_properties_agency_deleted');
            $table->foreign('agency_id', 'fk_properties_agency')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign(['agency_id', 'assigned_agent_id'], 'fk_properties_agent')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['agency_id', 'created_by_user_id'], 'fk_properties_creator')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_type CHECK (property_type IN ('apartment','house','villa','land','office','commercial','warehouse','other'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_transaction CHECK (transaction_type IN ('sale','rent'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_status CHECK (status IN ('available','reserved','sold','rented','archived'))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_money_positive CHECK ((sale_price IS NULL OR sale_price > 0) AND (deposit_amount IS NULL OR deposit_amount >= 0) AND (monthly_rent IS NULL OR monthly_rent > 0))');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_commercial CHECK ((transaction_type = 'sale' AND sale_price IS NOT NULL AND deposit_amount IS NULL AND monthly_rent IS NULL) OR (transaction_type = 'rent' AND sale_price IS NULL AND monthly_rent IS NOT NULL AND deposit_amount IS NOT NULL))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_area CHECK (area_sqm IS NULL OR area_sqm > 0)');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_closed CHECK ((status = 'sold' AND transaction_type = 'sale' AND closed_at IS NOT NULL) OR (status = 'rented' AND transaction_type = 'rent' AND closed_at IS NOT NULL) OR (status NOT IN ('sold','rented') AND closed_at IS NULL))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_archived CHECK ((status = 'archived' AND archived_at IS NOT NULL) OR (status <> 'archived' AND archived_at IS NULL))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_latitude CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_longitude CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_coordinates CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL))');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_floor CHECK (floor_number IS NULL OR total_floors IS NULL OR floor_number <= total_floors)');
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
