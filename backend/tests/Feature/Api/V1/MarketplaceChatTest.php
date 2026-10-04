<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\MarketplaceConversation;
use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\PublicUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class MarketplaceChatTest extends DomainTestCase
{
    public function test_public_chat_is_private_and_manager_and_assigned_responder_can_reply(): void
    {
        [$publication, $manager, $advisor] = $this->listing();
        $public = PublicUser::query()->create(['phone' => '+989121234501', 'is_active' => true]);
        Sanctum::actingAs($public, ['marketplace:public']);
        $id = $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'public'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'سلام'])->assertCreated();
        Sanctum::actingAs(PublicUser::query()->create(['phone' => '+989121234502', 'is_active' => true]), ['marketplace:public']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertNotFound();
        Sanctum::actingAs($advisor, ['mobile']);
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'پاسخ'])->assertCreated();
        $agency = $manager->agency;
        $this->assertNotNull($agency);
        $outsider = User::factory()->agent($agency)->create();
        Sanctum::actingAs($outsider, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertNotFound();
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(2, 'data');
        $publication->update(['responding_user_id' => $outsider->id]);
        Sanctum::actingAs($advisor, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertNotFound();
        Sanctum::actingAs($outsider, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertOk();
    }

    public function test_reserved_listing_preserves_text_history_but_blocks_sends_starts_and_attachment_fetches(): void
    {
        Storage::fake('local');
        [$publication] = $this->listing();
        $public = PublicUser::query()->create(['phone' => '+989121234503', 'is_active' => true]);
        Sanctum::actingAs($public, ['marketplace:public']);
        $id = $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'public'])->json('data.id');
        $image = $this->post('/api/v1/marketplace/conversations/'.$id.'/messages', ['image' => UploadedFile::fake()->image('chat.jpg')], ['Accept' => 'application/json'])->assertCreated()->json('data.image_url');
        $this->get($image)->assertOk();
        $publication->property()->firstOrFail()->update(['status' => PropertyStatus::Reserved]);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertOk();
        $this->getJson('/api/v1/marketplace/conversations')->assertJsonPath('data.0.can_send', false);
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'new'])->assertNotFound();
        $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'public'])->assertNotFound();
        $this->get($image, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_b2b_requester_side_only_initiator_and_manager_and_reads_are_monotonic(): void
    {
        [$publication] = $this->listing();
        app(TenantContext::class)->clear();
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $agency->forceFill(['is_verified' => true])->save();
        Sanctum::actingAs($agent, ['mobile']);
        $id = $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'agency'])->assertCreated()->json('data.id');
        $messageId = $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'one', 'client_message_id' => 'd0422225-5ef0-4d72-b5c7-8fdac2a625c8'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'retry', 'client_message_id' => 'd0422225-5ef0-4d72-b5c7-8fdac2a625c8'])->assertCreated()->assertJsonPath('data.id', $messageId);
        $this->assertSame(1, MarketplaceConversation::query()->findOrFail((int) $id)->messages()->count());
        Sanctum::actingAs(User::factory()->agent($agency)->create(), ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertNotFound();
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations')->assertJsonPath('data.0.unread_count', 1);
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/read', ['through_message_id' => $messageId])->assertOk();
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/read', ['through_message_id' => 0])->assertJsonPath('data.last_read_message_id', $messageId);
        $this->getJson('/api/v1/marketplace/conversations')->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_staff_starting_in_public_feed_keeps_requester_manager_access_without_granting_other_advisors(): void
    {
        [$publication] = $this->listing();
        $publication->update(['share_with_agencies' => false]);
        app(TenantContext::class)->clear();
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $agency->forceFill(['is_verified' => true])->save();
        Sanctum::actingAs($agent, ['mobile']);
        $id = $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'public'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('marketplace_conversations', ['id' => $id, 'audience' => 'public', 'requester_type' => 'user', 'requester_id' => $agent->id, 'requester_agency_id' => $agency->id]);
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'public feed inquiry'])->assertCreated();
        Sanctum::actingAs($manager, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'requester manager reply'])->assertCreated();
        Sanctum::actingAs($agent, ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(2, 'data');
        Sanctum::actingAs(User::factory()->agent($agency)->create(), ['mobile']);
        $this->getJson('/api/v1/marketplace/conversations/'.$id.'/messages')->assertNotFound();
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'forbidden reply'])->assertNotFound();
    }

    public function test_chat_rejects_non_images_and_guessed_attachment_ids_across_principals(): void
    {
        Storage::fake('local');
        [$publication] = $this->listing();
        $public = PublicUser::query()->create(['phone' => '+989121234504', 'is_active' => true]);
        Sanctum::actingAs($public, ['marketplace:public']);
        $id = $this->postJson('/api/v1/marketplace/listings/'.$publication->id.'/conversations', ['audience' => 'public'])->assertCreated()->json('data.id');
        $this->post('/api/v1/marketplace/conversations/'.$id.'/messages', ['image' => UploadedFile::fake()->create('document.pdf', 2, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
        $url = $this->post('/api/v1/marketplace/conversations/'.$id.'/messages', ['image' => UploadedFile::fake()->image('photo.jpg')], ['Accept' => 'application/json'])->assertCreated()->json('data.image_url');
        Sanctum::actingAs(PublicUser::query()->create(['phone' => '+989121234505', 'is_active' => true]), ['marketplace:public']);
        $this->get($url, ['Accept' => 'application/json'])->assertNotFound();
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/messages', ['body' => 'unauthorized'])->assertNotFound();
        $this->postJson('/api/v1/marketplace/conversations/'.$id.'/read', ['through_message_id' => 0])->assertNotFound();
    }

    /** @return array{PropertyPublication, User, User} */
    private function listing(): array
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $agency->forceFill(['is_verified' => true])->save();
        $this->establish($manager);
        $property = Property::factory()->forAgency($agency, $agent)->create();
        $publication = PropertyPublication::query()->create([
            'property_id' => $property->id, 'agency_id' => $agency->id,
            'share_with_agencies' => true, 'publish_public' => true,
            'public_title' => 'Public apartment', 'responding_user_id' => $agent->id,
        ]);

        return [$publication, $manager, $agent];
    }
}
