<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Http\Middleware\AssignRequestId;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_available_with_a_request_id(): void
    {
        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertJson([
                'name' => 'دفتر املاکی',
                'status' => 'ok',
            ])
            ->assertHeader(AssignRequestId::HEADER);

        self::assertMatchesRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{26}$/',
            (string) $response->headers->get(AssignRequestId::HEADER),
        );
    }
}
