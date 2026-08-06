<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $dashboard->for($user)]);
    }
}
