<?php

declare(strict_types=1);

use App\Domain\Property\Exceptions\FileTooLargeException;
use App\Domain\Property\Exceptions\UnsupportedImageTypeException;
use App\Domain\SavedFilter\Exceptions\InvalidFilterException;
use App\Domain\Shared\Exceptions\DomainConflictException;
use App\Domain\Shared\Exceptions\StaleRecordException;
use App\Domain\Tenancy\Exceptions\AgencyInactiveException;
use App\Domain\Tenancy\Exceptions\InactiveUserException;
use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\Exceptions\TenantMismatchException;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Domain\User\Exceptions\PasswordChangeRequiredException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureIdempotentClientOperation;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EstablishTenantContext;
use App\Http\Responses\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: static function (): void {
            Route::get('/up', static fn () => response()->json([
                'name' => 'ملک بان',
                'status' => 'ok',
            ]))->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            static fn (Request $request): ?string => $request->is('api/*')
                ? null
                : (Route::has('login') ? route('login') : '/agent/login'),
        );
        $middleware->prepend(AssignRequestId::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EstablishTenantContext::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'password.changed' => EnsurePasswordChanged::class,
            'tenant' => EstablishTenantContext::class,
            'idempotent' => EnsureIdempotentClientOperation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            AgencyInactiveException::class,
            DomainConflictException::class,
            FileTooLargeException::class,
            InactiveUserException::class,
            InvalidFilterException::class,
            InvalidCredentialsException::class,
            MissingTenantContextException::class,
            PasswordChangeRequiredException::class,
            TenantMismatchException::class,
            StaleRecordException::class,
            UnsupportedImageTypeException::class,
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(
            fn (Throwable $exception, Request $request) => app(ApiExceptionRenderer::class)
                ->render($exception, $request),
        );
    })->create();
