<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PublicUser;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMarketplacePrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|PublicUser|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User || $actor instanceof PublicUser, 401);
        abort_unless($actor->tokenCan($actor instanceof PublicUser ? 'marketplace:public' : 'mobile'), 403);

        return $next($request);
    }
}
