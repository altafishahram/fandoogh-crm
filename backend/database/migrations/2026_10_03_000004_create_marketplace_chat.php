<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_publication_id')->constrained()->restrictOnDelete();
            $table->foreignId('publisher_agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('audience', 10);
            $table->string('requester_type', 20);
            $table->unsignedBigInteger('requester_id');
            $table->foreignId('requester_agency_id')->nullable()->constrained('agencies')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['property_publication_id', 'audience', 'requester_type', 'requester_id'], 'marketplace_conversation_requester_unique');
        });
        Schema::create('marketplace_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->unsignedBigInteger('sender_id');
            $table->uuid('client_message_id')->nullable();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_mime', 30)->nullable();
            $table->timestamps();
            $table->index(['marketplace_conversation_id', 'id']);
            $table->unique(['marketplace_conversation_id', 'sender_type', 'sender_id', 'client_message_id'], 'marketplace_message_client_unique');
        });
        Schema::create('marketplace_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_conversation_id')->constrained(indexName: 'marketplace_read_conversation_fk')->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();
            $table->unique(['marketplace_conversation_id', 'actor_type', 'actor_id'], 'marketplace_read_actor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_conversation_reads');
        Schema::dropIfExists('marketplace_messages');
        Schema::dropIfExists('marketplace_conversations');
    }
};
