<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Application\Dashboard\Services\DashboardService;
use App\Application\Report\Data\ReportPeriod;
use App\Application\Report\Services\ReportService;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\DomainTestCase;

final class OperationalAggregateHardeningTest extends DomainTestCase
{
    public function test_dashboard_and_report_do_not_disclose_agents_from_another_agency(): void
    {
        ['manager' => $manager, 'agent' => $ownAgent] = $this->tenant();
        $foreignAgency = Agency::factory()->active()->create();
        $foreignAgent = User::factory()->agent($foreignAgency)->create();
        Sanctum::actingAs($manager, ['mobile']);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonFragment(['agent_id' => $ownAgent->getKey()])
            ->assertJsonMissing(['agent_id' => $foreignAgent->getKey()]);
        $this->getJson('/api/v1/reports/me?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonFragment(['agent_id' => $ownAgent->getKey()])
            ->assertJsonMissing(['agent_id' => $foreignAgent->getKey()]);
    }

    public function test_dashboard_and_report_use_bounded_query_counts_for_many_agents(): void
    {
        ['agency' => $agency, 'manager' => $manager] = $this->tenant();
        User::factory()->count(15)->agent($agency)->create();
        $this->establish($manager);

        Cache::flush();
        $manager->unsetRelation('roles');
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        app(DashboardService::class)->for($manager);
        $dashboardQueries = count(DB::connection()->getQueryLog());

        $manager->unsetRelation('roles');
        DB::connection()->flushQueryLog();
        app(ReportService::class)->for(
            $manager,
            new ReportPeriod('2026-01-01', '2026-01-31', 'Asia/Tehran'),
        );
        $reportQueries = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertLessThanOrEqual(10, $dashboardQueries);
        $this->assertLessThanOrEqual(12, $reportQueries);
    }
}
