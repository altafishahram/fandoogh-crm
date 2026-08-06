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
        Schema::create('agencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100);
            $table->string('email', 254);
            $table->string('phone', 32);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->string('locale', 10);
            $table->char('currency_code', 3);
            $table->boolean('is_active')->default(false);
            $table->dateTime('activated_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique('slug', 'uq_agencies_slug');
            $table->index(['is_active', 'name'], 'idx_agencies_active_name');
            $table->index('created_at', 'idx_agencies_created_at');
        });

        DB::statement("ALTER TABLE agencies ADD CONSTRAINT chk_agencies_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement('ALTER TABLE agencies ADD CONSTRAINT chk_agencies_country_upper CHECK (country_code = UPPER(country_code))');
        DB::statement('ALTER TABLE agencies ADD CONSTRAINT chk_agencies_currency_upper CHECK (currency_code = UPPER(currency_code))');
        DB::statement('ALTER TABLE agencies ADD CONSTRAINT chk_agencies_activation CHECK (is_active = FALSE OR activated_at IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
