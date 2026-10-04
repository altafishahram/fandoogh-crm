<?php

declare(strict_types=1);

namespace App\Filament\Shared\Support;

use App\Application\Geography\Services\LocationQuery;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class LocationFields
{
    public static function filter(string $prefix = ''): Filter
    {
        $fields = self::make($prefix, true);
        $fields[0]->helperText('برای مشاهده همه پرونده‌ها، استان را پاک کنید. موقعیت شهرهای قدیمی نامشخص را در ویرایش پرونده اصلاح کنید.');

        return Filter::make('location')->label('موقعیت')->schema($fields)
            ->query(function (Builder $query, array $data) use ($prefix): Builder {
                $filters = [];
                foreach (['province_id', 'county_id', 'city_id'] as $field) {
                    $filters[$field] = $data[$prefix.$field] ?? null;
                }

                return app(LocationQuery::class)->apply($query, $filters, $prefix);
            });
    }

    /** @return list<Select> */
    public static function make(string $prefix = '', bool $agencyDefaults = false): array
    {
        return [
            Select::make($prefix.'province_id')->label('استان')->searchable()->live()
                ->options(fn (): array => DB::table('location_provinces')->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => $agencyDefaults ? auth()->user()?->agency?->getAttribute('province_id') : null)
                ->afterStateUpdated(function (Set $set) use ($prefix): void {
                    $set($prefix.'county_id', null);
                    $set($prefix.'city_id', null);
                }),
            Select::make($prefix.'county_id')->label('شهرستان')->searchable()->live()
                ->options(fn (Get $get): array => DB::table('location_counties')->where('province_id', $get($prefix.'province_id'))->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => $agencyDefaults ? auth()->user()?->agency?->getAttribute('county_id') : null)
                ->afterStateUpdated(fn (Set $set) => $set($prefix.'city_id', null)),
            Select::make($prefix.'city_id')->label('شهر')->searchable()->live()
                ->options(fn (Get $get): array => DB::table('location_cities')->where('county_id', $get($prefix.'county_id'))->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => $agencyDefaults ? auth()->user()?->agency?->getAttribute('city_id') : null)
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get) use ($prefix): void {
                    if ($state !== null) {
                        $set($prefix.'city', DB::table('location_cities')->where('id', $state)->value('name'));
                        if ($prefix === '') {
                            $set('province', DB::table('location_provinces')->where('id', $get('province_id'))->value('name'));
                        }
                    }
                }),
        ];
    }
}
