<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Http\Middleware\AssignRequestId;
use Tests\TestCase;

final class RootEndpointTest extends TestCase
{
    public function test_root_endpoint_reports_foundation_status(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader(AssignRequestId::HEADER)
            ->assertExactJson([
                'name' => 'ملک بان',
                'phase' => 'آماده بهره‌برداری آزمایشی',
                'status' => 'فعال',
            ]);
    }
}
