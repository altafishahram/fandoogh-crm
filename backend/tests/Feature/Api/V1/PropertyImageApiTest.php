<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class PropertyImageApiTest extends DomainTestCase
{
    public function test_agent_uploads_private_image_and_reads_authenticated_content(): void
    {
        Storage::fake('local');
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $ownerId = $this->postJson('/api/v1/owners', [
            'owner_type' => 'person', 'first_name' => 'Ali', 'last_name' => 'Ahmadi',
            'mobile' => '+989121234567',
        ])->assertCreated()->json('data.id');
        $propertyId = $this->postJson('/api/v1/properties', [
            'title' => 'Image listing', 'property_type' => 'apartment', 'transaction_type' => 'sale',
            'sale_price' => '1000.00', 'city' => 'Tehran', 'street_address' => 'Test street',
            'owners' => [[
                'owner_id' => $ownerId, 'ownership_percentage' => '100.00', 'is_primary' => true,
            ]],
        ])->assertCreated()->json('data.id');

        $response = $this->post('/api/v1/properties/'.$propertyId.'/images', [
            'image' => UploadedFile::fake()->image('home.jpg', 120, 80),
        ], ['Accept' => 'application/json']);
        $response->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.is_cover', true)
            ->assertJsonMissingPath('data.storage_path');

        $this->get($response->json('data.content_url'), ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_non_image_upload_returns_stable_media_error(): void
    {
        Storage::fake('local');
        ['agent' => $agent] = $this->tenant();
        Sanctum::actingAs($agent, ['mobile']);
        $ownerId = $this->postJson('/api/v1/owners', [
            'owner_type' => 'person', 'first_name' => 'Ali', 'last_name' => 'Ahmadi',
            'mobile' => '+989121234567',
        ])->assertCreated()->json('data.id');
        $propertyId = $this->postJson('/api/v1/properties', [
            'title' => 'Image listing', 'property_type' => 'apartment', 'transaction_type' => 'sale',
            'sale_price' => '1000.00', 'city' => 'Tehran', 'street_address' => 'Test street',
            'owners' => [[
                'owner_id' => $ownerId, 'ownership_percentage' => '100.00', 'is_primary' => true,
            ]],
        ])->assertCreated()->json('data.id');

        $this->post('/api/v1/properties/'.$propertyId.'/images', [
            'image' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');
    }
}
