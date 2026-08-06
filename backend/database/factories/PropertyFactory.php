<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Enums\PropertyType;
use App\Domain\Property\Enums\TransactionType;
use App\Models\Agency;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
final class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'code' => 'TST-'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'property_type' => PropertyType::Apartment,
            'transaction_type' => TransactionType::Sale,
            'status' => PropertyStatus::Available,
            'currency_code' => 'IRR',
            'sale_price' => fake()->numberBetween(1_000_000, 9_000_000).'.00',
            'deposit_amount' => null,
            'monthly_rent' => null,
            'area_sqm' => '100.00',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'floor_number' => 1,
            'total_floors' => 4,
            'year_built' => 2020,
            'parking_spaces' => 1,
            'has_storage_room' => false,
            'has_elevator' => true,
            'has_balcony' => false,
            'city' => 'Tehran',
            'district' => null,
            'street_address' => fake()->streetAddress(),
            'postal_code' => null,
            'latitude' => null,
            'longitude' => null,
            'available_from' => null,
            'closed_at' => null,
            'archived_at' => null,
        ];
    }

    public function forAgency(Agency $agency, User $creator, ?User $agent = null): static
    {
        return $this
            ->state(fn (): array => [
                'agency_id' => $agency->getKey(),
                'assigned_agent_id' => $agent?->getKey(),
                'created_by_user_id' => $creator->getKey(),
                'currency_code' => $agency->currency_code,
            ])
            ->afterMaking(fn (Property $property) => $property->setRelation('agency', $agency));
    }

    public function rent(): static
    {
        return $this->state(fn (): array => [
            'transaction_type' => TransactionType::Rent,
            'sale_price' => null,
            'deposit_amount' => '0.00',
            'monthly_rent' => '100000.00',
        ]);
    }
}
