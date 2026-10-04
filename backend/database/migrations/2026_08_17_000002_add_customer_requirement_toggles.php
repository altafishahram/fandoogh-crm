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
            $table->boolean('has_parking')->default(false)->after('min_parking_spaces');
            $table->boolean('has_storage_room')->default(false)->after('has_parking');
            $table->boolean('owner_resides')->default(false)->after('has_storage_room');
        });

        DB::statement('UPDATE customers SET has_parking = CASE WHEN COALESCE(min_parking_spaces, 0) > 0 THEN 1 ELSE 0 END');
    }

    public function down(): void
    {
        if (DB::table('customers')
            ->where('has_parking', true)
            ->orWhere('has_storage_room', true)
            ->orWhere('owner_resides', true)
            ->exists()) {
            throw new RuntimeException('ابتدا ویژگی‌های جدید مشتریان را حذف کنید.');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['has_parking', 'has_storage_room', 'owner_resides']);
        });
    }
};
