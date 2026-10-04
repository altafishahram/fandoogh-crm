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
        Schema::table('customers', function (Blueprint $table): void {
            $table->json('toilet_types')->nullable()->after('min_bedrooms');
            $table->boolean('has_master_bathroom')->default(false)->after('toilet_types');
            $table->string('cabinet_type', 64)->nullable()->after('has_master_bathroom');
            $table->string('heating_type', 64)->nullable()->after('cabinet_type');
            $table->string('cooling_type', 64)->nullable()->after('heating_type');
            $table->string('flooring_type', 64)->nullable()->after('cooling_type');
            $table->string('renovation_status', 32)->nullable()->after('flooring_type');
            $table->string('building_orientation', 16)->nullable()->after('renovation_status');
            $table->string('deed_type', 32)->nullable()->after('building_orientation');
            $table->boolean('has_loan')->default(false)->after('deed_type');
            $table->boolean('is_exchangeable')->default(false)->after('has_loan');
            $table->boolean('has_pool')->default(false)->after('is_exchangeable');
            $table->boolean('has_jacuzzi')->default(false)->after('has_pool');
            $table->boolean('has_sauna')->default(false)->after('has_jacuzzi');
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_orientation CHECK (building_orientation IS NULL OR building_orientation IN ('north','south','east','west'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_deed_type CHECK (deed_type IS NULL OR deed_type IN ('single_page','booklet','contract','other'))");
    }

    public function down(): void
    {
        $hasFeatureData = DB::table('customers')
            ->whereNotNull('toilet_types')
            ->orWhere('has_master_bathroom', true)
            ->orWhereNotNull('cabinet_type')
            ->orWhereNotNull('heating_type')
            ->orWhereNotNull('cooling_type')
            ->orWhereNotNull('flooring_type')
            ->orWhereNotNull('renovation_status')
            ->orWhereNotNull('building_orientation')
            ->orWhereNotNull('deed_type')
            ->orWhere('has_loan', true)
            ->orWhere('is_exchangeable', true)
            ->orWhere('has_pool', true)
            ->orWhere('has_jacuzzi', true)
            ->orWhere('has_sauna', true)
            ->exists();

        if ($hasFeatureData) {
            throw new RuntimeException('بازگردانی این مهاجرت باعث حذف نیازهای ثبت‌شده مشتری می‌شود.');
        }

        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_deed_type');
        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_orientation');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'toilet_types', 'has_master_bathroom', 'cabinet_type', 'heating_type',
                'cooling_type', 'flooring_type', 'renovation_status', 'building_orientation',
                'deed_type', 'has_loan', 'is_exchangeable', 'has_pool', 'has_jacuzzi', 'has_sauna',
            ]);
        });
    }
};
