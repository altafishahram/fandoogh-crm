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
            $table->boolean('can_aggregate')->default(false)->after('building_area');
            $table->string('land_frontage', 64)->nullable()->after('can_aggregate');
        });
    }

    public function down(): void
    {
        $hasData = DB::table('properties')
            ->where('can_aggregate', true)
            ->orWhereNotNull('land_frontage')
            ->exists();

        if ($hasData) {
            throw new RuntimeException('بازگردانی این مهاجرت باعث حذف اطلاعات قابلیت تجمیع یا حد زمین می‌شود.');
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['can_aggregate', 'land_frontage']);
        });
    }
};
