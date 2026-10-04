<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\CustomerHistoryResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class CustomerHistoryController extends Controller
{
    public function index(Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('view', $customer);

        return CustomerHistoryResource::collection(
            $customer->histories()->orderByDesc('occurred_at')->orderByDesc('id')->paginate(50),
        );
    }
}
