<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWebPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password
            && ! $request->routeIs('filament.*.auth.profile', 'filament.*.auth.logout')) {
            $profileUrl = Filament::getProfileUrl();
            abort_unless(is_string($profileUrl), 403);

            return redirect()->to($profileUrl);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
