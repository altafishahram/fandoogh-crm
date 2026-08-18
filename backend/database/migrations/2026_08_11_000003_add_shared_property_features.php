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
            $table->boolean('has_master_bathroom')->default(false)->after('master_bedrooms');
            $table->string('heating_type', 64)->nullable()->after('heating_systems');
            $table->string('cooling_type', 64)->nullable()->after('cooling_systems');
            $table->string('building_orientation', 16)->nullable()->after('renovation_status');
            $table->string('deed_type', 32)->nullable()->after('building_orientation');
            $table->boolean('has_loan')->default(false)->after('deed_type');
            $table->boolean('is_exchangeable')->default(false)->after('has_loan');
            $table->boolean('has_pool')->default(false)->after('is_exchangeable');
            $table->boolean('has_jacuzzi')->default(false)->after('has_pool');
            $table->boolean('has_sauna')->default(false)->after('has_jacuzzi');
        });

        DB::statement('UPDATE properties SET has_master_bathroom = 1 WHERE master_bedrooms IS NOT NULL AND master_bedrooms > 0');
        DB::statement("UPDATE properties SET heating_type = JSON_UNQUOTE(JSON_EXTRACT(heating_systems, '$[0]')) WHERE JSON_LENGTH(heating_systems) > 0");
        DB::statement("UPDATE properties SET cooling_type = JSON_UNQUOTE(JSON_EXTRACT(cooling_systems, '$[0]')) WHERE JSON_LENGTH(cooling_systems) > 0");
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_type');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_type CHECK (property_type IN ('apartment','house_villa','shop','office','bureau','industrial','land_old_building','house','villa','land','commercial','warehouse','other'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_orientation CHECK (building_orientation IS NULL OR building_orientation IN ('north','south','east','west'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_deed_type CHECK (deed_type IS NULL OR deed_type IN ('single_page','booklet','contract','other'))");
    }

    public function down(): void
    {
        $hasNewData = DB::table('properties')
            ->where('property_type', 'bureau')
            ->orWhere('has_master_bathroom', true)
            ->orWhereNotNull('heating_type')
            ->orWhereNotNull('cooling_type')
            ->orWhereNotNull('building_orientation')
            ->orWhereNotNull('deed_type')
            ->orWhere('has_loan', true)
            ->orWhere('is_exchangeable', true)
            ->orWhere('has_pool', true)
            ->orWhere('has_jacuzzi', true)
            ->orWhere('has_sauna', true)
            ->exists();
        if ($hasNewData) {
            throw new RuntimeException('Rollback would discard shared property feature data.');
        }

        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_deed_type');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_orientation');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_type');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_type CHECK (property_type IN ('apartment','house_villa','shop','office','industrial','land_old_building','house','villa','land','commercial','warehouse','other'))");

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'has_master_bathroom', 'heating_type', 'cooling_type', 'building_orientation',
                'deed_type', 'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            ]);
        });
    }
};
