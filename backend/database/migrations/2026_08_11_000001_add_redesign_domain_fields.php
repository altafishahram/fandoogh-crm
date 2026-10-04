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
        Schema::table('owners', function (Blueprint $table): void {
            $table->string('full_name', 200)->nullable()->after('owner_type');
            $table->index(['agency_id', 'full_name'], 'idx_owners_agency_full_name');
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->string('currency_unit', 16)->default('toman')->after('currency_code');
            $table->string('plaque', 32)->nullable()->after('street_address');
            $table->unsignedSmallInteger('units_per_floor')->nullable()->after('total_floors');
            $table->unsignedTinyInteger('master_bedrooms')->nullable()->after('bathrooms');
            $table->json('toilet_types')->nullable()->after('master_bedrooms');
            $table->string('cabinet_type', 64)->nullable()->after('has_balcony');
            $table->json('heating_systems')->nullable()->after('cabinet_type');
            $table->json('cooling_systems')->nullable()->after('heating_systems');
            $table->string('flooring_type', 64)->nullable()->after('cooling_systems');
            $table->string('renovation_status', 32)->nullable()->after('flooring_type');
            $table->string('delivery_status', 24)->nullable()->after('available_from');
            $table->date('evacuation_date')->nullable()->after('delivery_status');
            $table->boolean('is_convertible')->default(false)->after('monthly_rent');
            $table->decimal('minimum_deposit', 15, 2)->nullable()->after('is_convertible');
            $table->string('created_by_role', 32)->nullable()->after('created_by_user_id');
            $table->unsignedBigInteger('lock_version')->default(1)->after('created_by_role');
            $table->index(['agency_id', 'created_at', 'id'], 'idx_properties_agency_created');
        });

        DB::statement('ALTER TABLE properties MODIFY city VARCHAR(100) NULL');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_type');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_money_positive');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_commercial');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_type CHECK (property_type IN ('apartment','house_villa','shop','office','industrial','land_old_building','house','villa','land','commercial','warehouse','other'))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_money_positive CHECK ((sale_price IS NULL OR sale_price > 0) AND (deposit_amount IS NULL OR deposit_amount >= 0) AND (monthly_rent IS NULL OR monthly_rent >= 0) AND (minimum_deposit IS NULL OR minimum_deposit >= 0))');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_commercial CHECK ((transaction_type = 'sale' AND sale_price IS NOT NULL AND deposit_amount IS NULL AND monthly_rent IS NULL) OR (transaction_type = 'rent' AND sale_price IS NULL AND monthly_rent IS NOT NULL AND deposit_amount IS NOT NULL))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_delivery CHECK ((delivery_status IS NULL AND evacuation_date IS NULL) OR (delivery_status IN ('ready','vacated') AND evacuation_date IS NULL) OR (delivery_status = 'dated' AND evacuation_date IS NOT NULL))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_conversion CHECK ((is_convertible = 0 AND minimum_deposit IS NULL) OR (is_convertible = 1 AND transaction_type = \'rent\' AND minimum_deposit IS NOT NULL AND minimum_deposit <= deposit_amount))');

        Schema::table('property_owner', function (Blueprint $table): void {
            $table->unique(['agency_id', 'property_id'], 'uq_property_owner_one_owner_per_property');
            $table->unique(['agency_id', 'owner_id'], 'uq_property_owner_no_owner_reuse');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('full_name', 200)->nullable()->after('created_by_user_id');
            $table->string('created_by_role', 32)->nullable()->after('created_by_user_id');
            $table->string('desired_property_type', 32)->nullable()->after('preferred_property_types');
            $table->decimal('rental_deposit_min', 15, 2)->nullable()->after('budget_max');
            $table->decimal('rental_deposit_max', 15, 2)->nullable()->after('rental_deposit_min');
            $table->decimal('rental_rent_min', 15, 2)->nullable()->after('rental_deposit_max');
            $table->decimal('rental_rent_max', 15, 2)->nullable()->after('rental_rent_min');
            $table->boolean('accepts_rent_conversion')->default(true)->after('rental_rent_max');
            $table->text('description')->nullable()->after('min_bedrooms');
            $table->unsignedBigInteger('lock_version')->default(1)->after('description');
            $table->index(['agency_id', 'created_at', 'id'], 'idx_customers_agency_created');
            $table->index(['agency_id', 'desired_property_type', 'intent'], 'idx_customers_agency_need');
        });

        DB::statement('ALTER TABLE customers MODIFY assigned_agent_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_status');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','finalized','withdrawn','transacted_elsewhere','inactive','converted','lost'))");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customers_rental_ranges CHECK ((rental_deposit_min IS NULL OR rental_deposit_min >= 0) AND (rental_deposit_max IS NULL OR rental_deposit_max >= rental_deposit_min) AND (rental_rent_min IS NULL OR rental_rent_min >= 0) AND (rental_rent_max IS NULL OR rental_rent_max >= rental_rent_min))');

        Schema::create('customer_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('changed_by_user_id');
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->json('changed_fields')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_customer_histories_agency_id_id');
            $table->index(['agency_id', 'customer_id', 'occurred_at'], 'idx_customer_histories_customer_time');
            $table->foreign(['agency_id', 'customer_id'], 'fk_customer_histories_customer')
                ->references(['agency_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['agency_id', 'changed_by_user_id'], 'fk_customer_histories_actor')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('client_operations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('operation_key');
            $table->string('request_hash', 64);
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps(6);

            $table->unique(['agency_id', 'user_id', 'operation_key'], 'uq_client_operations_actor_key');
            $table->index(['agency_id', 'created_at'], 'idx_client_operations_created');
            $table->foreign(['agency_id', 'user_id'], 'fk_client_operations_user')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_operations');
        Schema::dropIfExists('customer_histories');

        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_rental_ranges');
        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_status');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','inactive','converted','lost'))");
        DB::statement('ALTER TABLE customers MODIFY assigned_agent_id BIGINT UNSIGNED NOT NULL');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('idx_customers_agency_need');
            $table->dropIndex('idx_customers_agency_created');
            $table->dropColumn([
                'full_name', 'created_by_role', 'desired_property_type', 'rental_deposit_min',
                'rental_deposit_max', 'rental_rent_min', 'rental_rent_max', 'accepts_rent_conversion',
                'description', 'lock_version',
            ]);
        });

        Schema::table('property_owner', function (Blueprint $table): void {
            $table->dropUnique('uq_property_owner_one_owner_per_property');
            $table->dropUnique('uq_property_owner_no_owner_reuse');
        });

        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_conversion');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_delivery');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_commercial');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_money_positive');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_type');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_type CHECK (property_type IN ('apartment','house','villa','land','office','commercial','warehouse','other'))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT chk_properties_money_positive CHECK ((sale_price IS NULL OR sale_price > 0) AND (deposit_amount IS NULL OR deposit_amount >= 0) AND (monthly_rent IS NULL OR monthly_rent > 0))');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_commercial CHECK ((transaction_type = 'sale' AND sale_price IS NOT NULL AND deposit_amount IS NULL AND monthly_rent IS NULL) OR (transaction_type = 'rent' AND sale_price IS NULL AND monthly_rent IS NOT NULL AND deposit_amount IS NOT NULL))");
        DB::statement('ALTER TABLE properties MODIFY city VARCHAR(100) NOT NULL');
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropIndex('idx_properties_agency_created');
            $table->dropColumn([
                'currency_unit', 'plaque', 'units_per_floor', 'master_bedrooms', 'toilet_types',
                'cabinet_type', 'heating_systems', 'cooling_systems', 'flooring_type',
                'renovation_status', 'delivery_status', 'evacuation_date', 'is_convertible',
                'minimum_deposit', 'created_by_role', 'lock_version',
            ]);
        });

        Schema::table('owners', function (Blueprint $table): void {
            $table->dropIndex('idx_owners_agency_full_name');
            $table->dropColumn('full_name');
        });
    }
};
