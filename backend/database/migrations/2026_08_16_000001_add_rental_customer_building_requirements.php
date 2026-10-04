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
            $table->unsignedTinyInteger('min_parking_spaces')->nullable()->after('min_bedrooms');
            $table->boolean('has_elevator')->default(false)->after('min_parking_spaces');
            $table->boolean('has_balcony')->default(false)->after('has_elevator');
        });
    }

    public function down(): void
    {
        $hasRentalFeatureData = DB::table('customers')
            ->whereNotNull('min_parking_spaces')
            ->orWhere('has_elevator', true)
            ->orWhere('has_balcony', true)
            ->exists();

        if ($hasRentalFeatureData) {
            throw new RuntimeException('بازگردانی این مهاجرت باعث حذف ویژگی‌های ثبت‌شده مشتریان اجاره‌ای می‌شود.');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['min_parking_spaces', 'has_elevator', 'has_balcony']);
        });
    }
};
