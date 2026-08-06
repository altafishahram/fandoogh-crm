<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Report\Data\ReportPeriod;
use App\Application\Report\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $timezone = (string) ($user->agency()->value('timezone') ?? 'UTC');

        return response()->json([
            'data' => $reports->for($user, new ReportPeriod($validated['from'], $validated['to'], $timezone)),
        ]);
    }
}
