<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_delivery');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_commercial');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_delivery CHECK (
            (delivery_status IS NULL AND evacuation_date IS NULL)
            OR (delivery_status IN ('ready', 'vacated', 'owner_occupied') AND evacuation_date IS NULL)
            OR (delivery_status IN ('dated', 'tenant_occupied') AND evacuation_date IS NOT NULL)
        )");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_commercial CHECK (
            (transaction_type = 'sale' AND sale_price IS NOT NULL AND
                (delivery_status = 'tenant_occupied'
                    OR (deposit_amount IS NULL AND monthly_rent IS NULL)))
            OR (transaction_type = 'rent' AND sale_price IS NULL
                AND monthly_rent IS NOT NULL AND deposit_amount IS NOT NULL)
        )");
    }

    public function down(): void
    {
        if (DB::table('properties')->whereIn('delivery_status', ['owner_occupied', 'tenant_occupied'])->exists()) {
            throw new RuntimeException('ابتدا املاک دارای وضعیت سکونت جدید را به وضعیت قدیمی تبدیل کنید.');
        }
        if (DB::table('properties')->where('transaction_type', 'sale')
            ->where(static function ($query): void {
                $query->whereNotNull('deposit_amount')->orWhereNotNull('monthly_rent');
            })->exists()) {
            throw new RuntimeException('ابتدا مبالغ مستأجرِ املاک فروشی را حذف کنید.');
        }

        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_delivery');
        DB::statement('ALTER TABLE properties DROP CHECK chk_properties_commercial');
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_delivery CHECK (
            (delivery_status IS NULL AND evacuation_date IS NULL)
            OR (delivery_status IN ('ready', 'vacated') AND evacuation_date IS NULL)
            OR (delivery_status = 'dated' AND evacuation_date IS NOT NULL)
        )");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT chk_properties_commercial CHECK (
            (transaction_type = 'sale' AND sale_price IS NOT NULL
                AND deposit_amount IS NULL AND monthly_rent IS NULL)
            OR (transaction_type = 'rent' AND sale_price IS NULL
                AND monthly_rent IS NOT NULL AND deposit_amount IS NOT NULL)
        )");
    }
};
