<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('name', 150);
            $table->string('email', 254);
            $table->string('phone', 32)->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('last_login_at', 6)->nullable();
            $table->rememberToken();
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique('email', 'uq_users_email');
            $table->unique(['agency_id', 'id'], 'uq_users_agency_id_id');
            $table->index(['agency_id', 'is_active', 'name'], 'idx_users_agency_active_name');
            $table->index(['agency_id', 'deleted_at'], 'idx_users_agency_deleted');
            $table->foreign('agency_id', 'fk_users_agency')
                ->references('id')
                ->on('agencies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
