<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Tests\Support\DomainTestCase;

final class MatchNotificationPanelTest extends DomainTestCase
{
    public function test_agent_and_manager_can_open_their_notification_resource(): void
    {
        ['manager' => $manager, 'agent' => $agent] = $this->tenant();

        $this->actingAs($manager)->get('/agency/match-notifications')
            ->assertOk()
            ->assertSee('اعلان‌های تطبیق');

        $this->actingAs($agent)->get('/agent/match-notifications')
            ->assertOk()
            ->assertSee('اعلان‌های تطبیق');
    }

    public function test_each_role_cannot_open_the_other_role_notification_resource(): void
    {
        ['manager' => $manager, 'agent' => $agent] = $this->tenant();

        $this->actingAs($manager)->get('/agent/match-notifications')->assertForbidden();
        $this->actingAs($agent)->get('/agency/match-notifications')->assertForbidden();
    }
}
