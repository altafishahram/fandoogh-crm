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
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('building_type', 32)->nullable()->after('property_type');
            $table->string('structure_type', 160)->nullable()->after('building_type');
            $table->boolean('has_water')->default(false)->after('structure_type');
            $table->boolean('has_electricity')->default(false)->after('has_water');
            $table->boolean('has_gas')->default(false)->after('has_electricity');
            $table->string('telephone_line_count', 32)->nullable()->after('has_gas');
            $table->string('land_area', 64)->nullable()->after('telephone_line_count');
            $table->string('building_area', 64)->nullable()->after('land_area');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('building_type', 32)->nullable()->after('desired_property_type');
            $table->string('structure_type', 160)->nullable()->after('building_type');
            $table->boolean('has_water')->default(false)->after('structure_type');
            $table->boolean('has_electricity')->default(false)->after('has_water');
            $table->boolean('has_gas')->default(false)->after('has_electricity');
            $table->string('telephone_line_count', 32)->nullable()->after('has_gas');
            $table->string('land_area', 64)->nullable()->after('telephone_line_count');
            $table->string('building_area', 64)->nullable()->after('land_area');
        });

        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_building_type CHECK (building_type IS NULL OR building_type IN ('detached','township','duplex','triplex','apartment_villa'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_building_type CHECK (building_type IS NULL OR building_type IN ('detached','township','duplex','triplex','apartment_villa'))");
    }

    public function down(): void
    {
        $propertyHasData = DB::table('properties')
            ->whereNotNull('building_type')
            ->orWhereNotNull('structure_type')
            ->orWhere('has_water', true)
            ->orWhere('has_electricity', true)
            ->orWhere('has_gas', true)
            ->orWhereNotNull('telephone_line_count')
            ->orWhereNotNull('land_area')
            ->orWhereNotNull('building_area')
            ->exists();
        $customerHasData = DB::table('customers')
            ->whereNotNull('building_type')
            ->orWhereNotNull('structure_type')
            ->orWhere('has_water', true)
            ->orWhere('has_electricity', true)
            ->orWhere('has_gas', true)
            ->orWhereNotNull('telephone_line_count')
            ->orWhereNotNull('land_area')
            ->orWhereNotNull('building_area')
            ->exists();

        if ($propertyHasData || $customerHasData) {
            throw new RuntimeException('بازگردانی این مهاجرت باعث حذف اطلاعات ویلایی یا صنعتی می‌شود.');
        }

        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_building_type');
        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_building_type');

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
                'telephone_line_count', 'land_area', 'building_area',
            ]);
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'building_type', 'structure_type', 'has_water', 'has_electricity', 'has_gas',
                'telephone_line_count', 'land_area', 'building_area',
            ]);
        });
    }
};
