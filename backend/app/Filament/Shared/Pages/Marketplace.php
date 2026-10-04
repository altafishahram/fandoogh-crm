<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Application\Marketplace\Services\ConversationService;
use App\Application\Marketplace\Services\ListingAccessService;
use App\Filament\Agency\Pages\Conversations;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

abstract class Marketplace extends Page
{
    use WithPagination;

    protected static ?string $title = 'بازار همکاری املاک';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected string $view = 'filament.marketplace.browse';

    public string $search = '';

    public ?int $selectedListing = null;

    public ?int $provinceFilter = null;

    public ?int $countyFilter = null;

    public ?int $cityFilter = null;

    public bool $selectedListingClosed = false;

    public function mount(): void
    {
        $agency = $this->actor()->agency;
        $this->provinceFilter = $agency?->province_id;
        $this->countyFilter = $agency?->county_id;
        $this->cityFilter = $agency?->city_id;
    }

    public static function canAccess(): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return false;
        }
        $agency = $actor->agency;

        return $actor->is_active && $agency !== null && $agency->is_active && $agency->is_verified;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProvinceFilter(): void
    {
        $this->countyFilter = null;
        $this->cityFilter = null;
        $this->resetPage();
    }

    public function updatedCountyFilter(): void
    {
        $this->cityFilter = null;
        $this->resetPage();
    }

    public function updatedCityFilter(): void
    {
        $this->resetPage();
    }

    public function selectListing(int $id): void
    {
        app(ListingAccessService::class)->findVisible($id, $this->actor(), 'agency');
        $this->selectedListing = $id;
        $this->selectedListingClosed = false;
    }

    public function startConversation(): void
    {
        abort_unless($this->selectedListing !== null, 422);
        $conversation = app(ConversationService::class)->start($this->actor(), $this->selectedListing, 'agency');
        $page = $this->actor()->roleName()->value === 'agency-manager'
            ? Conversations::class : \App\Filament\Agent\Pages\Conversations::class;
        $this->redirect($page::getUrl(['conversation' => $conversation->id]));
    }

    public function getViewData(): array
    {
        $actor = $this->actor();
        $service = app(ListingAccessService::class);
        $query = $service->visibleQuery($actor, 'agency')->where('agency_id', '!=', $actor->agency_id);
        if ($this->search !== '') {
            $query->where('public_title', 'like', '%'.$this->search.'%');
        }
        foreach (['province' => $this->provinceFilter, 'county' => $this->countyFilter, 'city' => $this->cityFilter] as $name => $id) {
            if ($id !== null) {
                $query->whereHas('property', fn (Builder $property) => $property->where($name.'_id', $id));
            }
        }
        $selected = null;
        if ($this->selectedListing !== null) {
            $selected = $service->visibleQuery($actor, 'agency')->find($this->selectedListing);
            if ($selected === null) {
                $this->selectedListing = null;
                $this->selectedListingClosed = true;
            }
        }

        return ['listings' => $query->latest('id')->paginate(12), 'selected' => $selected];
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
