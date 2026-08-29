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
            $table->dateTime('matching_eligible_at', 6)->nullable()->after('deleted_at');
            $table->index(
                ['agency_id', 'status', 'matching_eligible_at'],
                'idx_properties_matching_eligibility',
            );
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->decimal('land_area_min', 10, 2)->nullable()->after('land_area');
            $table->decimal('land_area_max', 10, 2)->nullable()->after('land_area_min');
            $table->decimal('building_area_min', 10, 2)->nullable()->after('building_area');
            $table->decimal('building_area_max', 10, 2)->nullable()->after('building_area_min');
            $table->dateTime('matching_eligible_at', 6)->nullable()->after('deleted_at');
            $table->index(
                ['agency_id', 'status', 'matching_eligible_at'],
                'idx_customers_matching_eligibility',
            );
        });

        DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customers_industrial_area_ranges CHECK (
            (land_area_min IS NULL OR land_area_min >= 0)
            AND (land_area_max IS NULL OR land_area_max >= 0)
            AND (land_area_min IS NULL OR land_area_max IS NULL OR land_area_min <= land_area_max)
            AND (building_area_min IS NULL OR building_area_min >= 0)
            AND (building_area_max IS NULL OR building_area_max >= 0)
            AND (building_area_min IS NULL OR building_area_max IS NULL OR building_area_min <= building_area_max)
        )');

        Schema::create('property_customer_matches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('score', 5, 2);
            $table->decimal('financial_score', 5, 2);
            $table->decimal('area_score', 5, 2);
            $table->decimal('feature_score', 5, 2);
            $table->string('match_mode', 16);
            $table->unsignedSmallInteger('property_rank')->nullable();
            $table->unsignedSmallInteger('customer_rank')->nullable();
            $table->decimal('matched_deposit_min', 15, 2)->nullable();
            $table->decimal('matched_deposit_max', 15, 2)->nullable();
            $table->decimal('matched_rent_min', 15, 2)->nullable();
            $table->decimal('matched_rent_max', 15, 2)->nullable();
            $table->string('fingerprint', 64);
            $table->timestamps(6);

            $table->unique(['agency_id', 'id'], 'uq_property_customer_matches_agency_id_id');
            $table->unique(
                ['agency_id', 'property_id', 'customer_id'],
                'uq_property_customer_matches_pair',
            );
            $table->index(
                ['agency_id', 'property_id', 'score', 'customer_rank'],
                'idx_property_customer_matches_property_rank',
            );
            $table->index(
                ['agency_id', 'customer_id', 'score', 'property_rank'],
                'idx_property_customer_matches_customer_rank',
            );
            $table->foreign('agency_id', 'fk_matches_agency')
                ->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign(['agency_id', 'property_id'], 'fk_matches_property')
                ->references(['agency_id', 'id'])->on('properties')->cascadeOnDelete();
            $table->foreign(['agency_id', 'customer_id'], 'fk_matches_customer')
                ->references(['agency_id', 'id'])->on('customers')->cascadeOnDelete();
        });

        Schema::create('match_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('property_customer_match_id');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('title', 200);
            $table->text('body');
            $table->timestamps(6);

            $table->unique(['agency_id', 'id'], 'uq_match_notifications_agency_id_id');
            $table->unique(
                ['agency_id', 'property_customer_match_id'],
                'uq_match_notifications_match',
            );
            $table->index(
                ['agency_id', 'created_at'],
                'idx_match_notifications_agency_created',
            );
            $table->foreign('agency_id', 'fk_match_notifications_agency')
                ->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign(['agency_id', 'property_customer_match_id'], 'fk_match_notifications_match')
                ->references(['agency_id', 'id'])->on('property_customer_matches')->cascadeOnDelete();
        });

        Schema::create('match_notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('match_notification_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('notification_version');
            $table->dateTime('read_at', 6);
            $table->timestamps(6);

            $table->unique(
                ['agency_id', 'match_notification_id', 'user_id'],
                'uq_match_notification_reads_user',
            );
            $table->index(
                ['agency_id', 'user_id', 'read_at'],
                'idx_match_notification_reads_user',
            );
            $table->foreign('agency_id', 'fk_match_notification_reads_agency')
                ->references('id')->on('agencies')->cascadeOnDelete();
            $table->foreign(['agency_id', 'match_notification_id'], 'fk_match_notification_reads_notification')
                ->references(['agency_id', 'id'])->on('match_notifications')->cascadeOnDelete();
            $table->foreign(['agency_id', 'user_id'], 'fk_match_notification_reads_user')
                ->references(['agency_id', 'id'])->on('users')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE property_customer_matches ADD CONSTRAINT chk_property_customer_matches_mode CHECK (match_mode IN ('direct', 'converted'))");
        DB::statement('ALTER TABLE property_customer_matches ADD CONSTRAINT chk_property_customer_matches_score CHECK (
            score BETWEEN 0 AND 100
            AND financial_score BETWEEN 0 AND 55
            AND area_score BETWEEN 0 AND 25
            AND feature_score BETWEEN 0 AND 20
            AND property_rank BETWEEN 1 AND 10
            AND customer_rank BETWEEN 1 AND 10
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('match_notification_reads');
        Schema::dropIfExists('match_notifications');
        Schema::dropIfExists('property_customer_matches');

        DB::statement('ALTER TABLE customers DROP CHECK chk_customers_industrial_area_ranges');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('idx_customers_matching_eligibility');
            $table->dropColumn([
                'land_area_min', 'land_area_max', 'building_area_min', 'building_area_max',
                'matching_eligible_at',
            ]);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropIndex('idx_properties_matching_eligibility');
            $table->dropColumn('matching_eligible_at');
        });
    }
};
