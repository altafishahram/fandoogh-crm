<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original rank columns are nullable; a null rank means outside that side's top twenty.
        DB::statement('ALTER TABLE property_customer_matches
            DROP CHECK chk_property_customer_matches_score,
            ADD CONSTRAINT chk_property_customer_matches_score CHECK (
                score BETWEEN 0 AND 100
                AND financial_score BETWEEN 0 AND 55
                AND area_score BETWEEN 0 AND 25
                AND feature_score BETWEEN 0 AND 20
                AND (property_rank IS NULL OR property_rank BETWEEN 1 AND 20)
                AND (customer_rank IS NULL OR customer_rank BETWEEN 1 AND 20)
                AND (property_rank IS NOT NULL OR customer_rank IS NOT NULL)
            ),
            ADD INDEX idx_matches_property_display_rank (agency_id, property_id, property_rank),
            ADD INDEX idx_matches_customer_display_rank (agency_id, customer_id, customer_rank)');
    }

    public function down(): void
    {
        if (DB::table('property_customer_matches')
            ->whereNull('property_rank')
            ->orWhereNull('customer_rank')
            ->orWhere('property_rank', '>', 10)
            ->orWhere('customer_rank', '>', 10)
            ->exists()) {
            throw new RuntimeException(
                'Cannot restore mutual top-ten matching while independent top-twenty pairs exist. '
                .'Preserve these matches and their notifications before rolling back; no records were deleted.',
            );
        }

        DB::statement('ALTER TABLE property_customer_matches
            DROP INDEX idx_matches_property_display_rank,
            DROP INDEX idx_matches_customer_display_rank,
            DROP CHECK chk_property_customer_matches_score,
            ADD CONSTRAINT chk_property_customer_matches_score CHECK (
                score BETWEEN 0 AND 100
                AND financial_score BETWEEN 0 AND 55
                AND area_score BETWEEN 0 AND 25
                AND feature_score BETWEEN 0 AND 20
                AND property_rank BETWEEN 1 AND 10
                AND customer_rank BETWEEN 1 AND 10
            )');
    }
};
