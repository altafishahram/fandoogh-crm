<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->char('token', 64);
            $table->text('abilities')->nullable();
            $table->dateTime('last_used_at', 6)->nullable();
            $table->dateTime('expires_at', 6);
            $table->timestamps(6);

            $table->unique('token', 'uq_personal_access_tokens_token');
            $table->index(['tokenable_type', 'tokenable_id'], 'idx_personal_access_tokens_owner');
            $table->index('expires_at', 'idx_personal_access_tokens_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
