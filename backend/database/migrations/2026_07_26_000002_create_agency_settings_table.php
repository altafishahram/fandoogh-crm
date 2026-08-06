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
        Schema::create('agency_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('agency_id')->primary();
            $table->string('property_code_prefix', 8);
            $table->unsignedBigInteger('next_property_sequence')->default(1);
            $table->unsignedTinyInteger('default_page_size')->default(25);
            $table->timestamps(6);

            $table->foreign('agency_id', 'fk_agency_settings_agency')
                ->references('id')
                ->on('agencies')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE agency_settings ADD CONSTRAINT chk_agency_settings_sequence CHECK (next_property_sequence >= 1)');
        DB::statement('ALTER TABLE agency_settings ADD CONSTRAINT chk_agency_settings_page_size CHECK (default_page_size IN (10, 25, 50, 100))');
        DB::statement("ALTER TABLE agency_settings ADD CONSTRAINT chk_agency_settings_prefix CHECK (property_code_prefix REGEXP '^[A-Z0-9]{2,8}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_settings');
    }
};
