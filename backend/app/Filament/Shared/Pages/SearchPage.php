<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Application\Search\Services\GlobalSearchService;
use App\Models\User;
use Filament\Pages\Page;

abstract class SearchPage extends Page
{
    protected string $view = 'filament.shared.search';

    public function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $query = trim((string) request()->query('q', ''));
        $results = mb_strlen($query) >= 2 && mb_strlen($query) <= 100
            ? app(GlobalSearchService::class)->search($user, $query)
            : ['properties' => [], 'owners' => [], 'customers' => []];

        return compact('query', 'results');
    }
}
