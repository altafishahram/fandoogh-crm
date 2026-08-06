<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return response()->json(['data' => $search->search($user, $validated['q'])]);
    }
}
