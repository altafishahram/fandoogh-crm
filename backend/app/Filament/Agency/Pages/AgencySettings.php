<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Application\Agency\Services\UpdateAgencyService;
use App\Application\Agency\Services\UpdateAgencySettingsService;
use App\Domain\User\Enums\PermissionName;
use App\Filament\Shared\Support\LocationFields;
use App\Filament\Shared\Support\PersianLabels;
use App\Models\Agency;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

final class AgencySettings extends Page
{
    protected static ?string $title = 'تنظیمات آژانس';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.agency.pages.agency-settings';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(PermissionName::AgencySettingsView->value);
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('update')->label('ویرایش تنظیمات')->schema([
                TextInput::make('email')->label(PersianLabels::field('email'))->email()->required(), TextInput::make('phone')->label(PersianLabels::field('phone'))->tel()->required(),
                TextInput::make('address_line_1')->label(PersianLabels::field('address_line_1'))->required(), TextInput::make('address_line_2')->label(PersianLabels::field('address_line_2')),
                ...LocationFields::make(),
                TextInput::make('city')->label(PersianLabels::field('city'))->required(), TextInput::make('province')->label(PersianLabels::field('province'))->required(), TextInput::make('postal_code')->label(PersianLabels::field('postal_code')),
                TextInput::make('timezone')->label(PersianLabels::field('timezone'))->required(), TextInput::make('locale')->label(PersianLabels::field('locale'))->required(),
                TextInput::make('property_code_prefix')->label(PersianLabels::field('property_code_prefix'))->required()->minLength(2)->maxLength(10)
                    ->regex('/^[A-Za-z0-9]{2,10}$/')
                    ->validationMessages(['regex' => 'پیشوند کد ملک باید شامل ۲ تا ۱۰ حرف انگلیسی یا عدد باشد.'])
                    ->helperText('فقط حروف انگلیسی یا عدد؛ نمونه: MLK'),
                Select::make('default_page_size')->label(PersianLabels::field('default_page_size'))->required()->options([10 => '10', 25 => '25', 50 => '50', 100 => '100']),
            ])->fillForm(function (): array {
                $agency = $this->agency();
                $settings = $agency->settings()->firstOrFail();

                return [
                    'email' => $agency->email, 'phone' => $agency->phone,
                    'address_line_1' => $agency->address_line_1, 'address_line_2' => $agency->address_line_2,
                    'province_id' => $agency->province_id, 'county_id' => $agency->county_id, 'city_id' => $agency->city_id,
                    'city' => $agency->city, 'province' => $agency->province, 'postal_code' => $agency->postal_code,
                    'timezone' => $agency->timezone, 'locale' => $agency->locale,
                    'property_code_prefix' => $settings->property_code_prefix,
                    'default_page_size' => $settings->default_page_size,
                ];
            })->action(function (array $data): void {
                $actor = $this->actor();
                $agency = $this->agency();
                DB::transaction(function () use ($actor, $agency, $data): void {
                    app(UpdateAgencyService::class)->execute($actor, $agency, $data);
                    app(UpdateAgencySettingsService::class)->execute(
                        $actor, $agency, (string) $data['property_code_prefix'], (int) $data['default_page_size'],
                    );
                });
            })->visible(fn (): bool => $this->actor()->can(PermissionName::AgencySettingsUpdate->value)),
        ];
    }

    public function getViewData(): array
    {
        $agency = $this->agency()->load('settings');

        return compact('agency');
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function agency(): Agency
    {
        return $this->actor()->agency()->firstOrFail();
    }
}
