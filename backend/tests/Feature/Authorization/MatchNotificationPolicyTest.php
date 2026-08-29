<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\MatchNotification;
use App\Policies\MatchNotificationPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Tests\Support\DomainTestCase;

final class MatchNotificationPolicyTest extends DomainTestCase
{
    public function test_agent_and_manager_can_read_notifications_of_their_own_agency(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $notification = new MatchNotification;
        $notification->agency_id = $agency->getKey();
        $policy = new MatchNotificationPolicy;

        self::assertTrue($policy->viewAny($agent));
        self::assertTrue($policy->view($agent, $notification));
        self::assertTrue($policy->markAsRead($agent, $notification));
        self::assertTrue($policy->viewAny($manager));
        self::assertTrue($policy->view($manager, $notification));
    }

    public function test_other_agency_and_inactive_users_cannot_read_notifications(): void
    {
        ['agency' => $agency, 'agent' => $agent] = $this->tenant();
        ['agent' => $otherAgent] = $this->tenant();
        $notification = new MatchNotification;
        $notification->agency_id = $agency->getKey();
        $inactiveAgent = (clone $agent);
        $inactiveAgent->is_active = false;
        $policy = new MatchNotificationPolicy;

        self::assertFalse($policy->view($otherAgent, $notification));
        self::assertFalse($policy->markAsRead($otherAgent, $notification));
        self::assertFalse($policy->viewAny($inactiveAgent));
    }

    public function test_policy_registration_uses_the_concrete_notification_model(): void
    {
        $notification = new MatchNotification;

        self::assertInstanceOf(Model::class, $notification);
        self::assertInstanceOf(MatchNotificationPolicy::class, Gate::getPolicyFor(MatchNotification::class));
    }
}
