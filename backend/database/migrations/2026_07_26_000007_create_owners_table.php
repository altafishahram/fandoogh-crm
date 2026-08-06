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
        Schema::create('owners', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->string('owner_type', 20);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 180)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->text('identity_number_encrypted')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_owners_agency_id_id');
            $table->index(['agency_id', 'last_name', 'first_name'], 'idx_owners_agency_person_name');
            $table->index(['agency_id', 'company_name'], 'idx_owners_agency_company');
            $table->index(['agency_id', 'mobile'], 'idx_owners_agency_mobile');
            $table->index(['agency_id', 'email'], 'idx_owners_agency_email');
            $table->index(['agency_id', 'deleted_at'], 'idx_owners_agency_deleted');
            $table->foreign('agency_id', 'fk_owners_agency')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign(['agency_id', 'created_by_user_id'], 'fk_owners_creator')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE owners ADD CONSTRAINT chk_owners_type CHECK (owner_type IN ('person', 'company'))");
        DB::statement("ALTER TABLE owners ADD CONSTRAINT chk_owners_name CHECK ((owner_type = 'person' AND first_name IS NOT NULL AND last_name IS NOT NULL) OR (owner_type = 'company' AND company_name IS NOT NULL))");
        DB::statement('ALTER TABLE owners ADD CONSTRAINT chk_owners_contact CHECK (mobile IS NOT NULL OR phone IS NOT NULL OR email IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
