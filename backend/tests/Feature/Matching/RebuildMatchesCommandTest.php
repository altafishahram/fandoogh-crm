<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Models\Customer;
use App\Models\Property;
use Illuminate\Testing\PendingCommand;
use Tests\Support\DomainTestCase;

final class RebuildMatchesCommandTest extends DomainTestCase
{
    public function test_command_rebuilds_only_eligible_records_and_is_repeatable(): void
    {
        ['agency' => $agency, 'manager' => $manager, 'agent' => $agent] = $this->tenant();
        $this->establish($manager);
        Property::factory()->forAgency($agency, $manager, $agent)->create([
            'sale_price' => '5000000.00', 'matching_eligible_at' => now(),
        ]);
        Property::factory()->forAgency($agency, $manager, $agent)->create([
            'sale_price' => '5000000.00', 'matching_eligible_at' => null,
        ]);
        Customer::factory()->forAgency($agency, $manager, $agent)->create([
            'desired_property_type' => 'apartment', 'budget_min' => '4000000.00',
            'budget_max' => '6000000.00', 'min_area_sqm' => '90.00',
            'max_area_sqm' => '110.00', 'matching_eligible_at' => now(),
        ]);

        $command = $this->artisan('matching:rebuild', ['--agency' => [(string) $agency->getKey()]]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
        $this->assertDatabaseCount('property_customer_matches', 1);
        $this->assertDatabaseCount('match_notifications', 1);
        $command = $this->artisan('matching:rebuild', ['--agency' => [(string) $agency->getKey()]]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
        $this->assertDatabaseCount('property_customer_matches', 1);
        $this->assertDatabaseCount('match_notifications', 1);
        $this->assertDatabaseCount('match_notification_reads', 0);
    }

    public function test_invalid_agency_argument_is_rejected_before_any_rebuild(): void
    {
        $command = $this->artisan('matching:rebuild', ['--agency' => ['-1']]);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(2)->run();
        $this->assertDatabaseCount('property_customer_matches', 0);
    }
}
