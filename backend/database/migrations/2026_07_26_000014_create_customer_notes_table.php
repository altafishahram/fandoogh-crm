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
        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('author_user_id');
            $table->text('body');
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->unique(['agency_id', 'id'], 'uq_customer_notes_agency_id_id');
            $table->index(['agency_id', 'customer_id', 'deleted_at', 'created_at'], 'idx_customer_notes_customer_created');
            $table->index(['agency_id', 'author_user_id', 'created_at'], 'idx_customer_notes_author');
            $table->foreign(['agency_id', 'customer_id'], 'fk_customer_notes_customer')
                ->references(['agency_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['agency_id', 'author_user_id'], 'fk_customer_notes_author')
                ->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_notes ADD CONSTRAINT chk_customer_notes_body CHECK (CHAR_LENGTH(TRIM(body)) BETWEEN 1 AND 5000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
