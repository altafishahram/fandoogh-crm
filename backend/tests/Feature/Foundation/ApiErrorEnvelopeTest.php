<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Http\Middleware\AssignRequestId;
use Tests\TestCase;

final class ApiErrorEnvelopeTest extends TestCase
{
    public function test_api_validation_uses_persian_field_names(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath(
                'error.details.device_name.0',
                'واردکردن نام دستگاه الزامی است.',
            );
    }

    public function test_unknown_api_route_uses_the_standard_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/not-found');

        $response
            ->assertNotFound()
            ->assertHeader(AssignRequestId::HEADER)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'اطلاعات درخواستی پیدا نشد.')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'request_id',
                ],
            ]);

        self::assertSame(
            $response->headers->get(AssignRequestId::HEADER),
            $response->json('error.request_id'),
        );
    }
}
