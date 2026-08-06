<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Support\IdentityTestCase;

final class DatabaseSessionTest extends IdentityTestCase
{
    public function test_web_login_rotates_and_persists_a_database_session(): void
    {
        Route::middleware('web')->withoutMiddleware(PreventRequestForgery::class)->post(
            '/_test/session-login/{user}',
            static function (Request $request, User $user) {
                $before = $request->session()->getId();
                Auth::guard('web')->login($user);
                $request->session()->regenerate();

                return response()->json([
                    'before' => $before,
                    'after' => $request->session()->getId(),
                ]);
            },
        );
        $agency = Agency::factory()->active()->create();
        $manager = User::factory()->agencyManager($agency)->create();

        $response = $this->postJson('/_test/session-login/'.$manager->getKey());

        $response->assertOk();
        self::assertNotSame($response->json('before'), $response->json('after'));
        $this->assertDatabaseHas('sessions', [
            'id' => $response->json('after'),
            'user_id' => $manager->getKey(),
        ]);
    }

    public function test_session_cookie_security_defaults_match_the_mvp(): void
    {
        self::assertSame('database', config('session.driver'));
        self::assertTrue(config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
        self::assertSame('json', config('session.serialization'));
    }
}
