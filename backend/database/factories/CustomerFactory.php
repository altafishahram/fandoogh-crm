<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customer\Enums\CustomerIntent;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Enums\PreferredContactMethod;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mobile' => '+989'.fake()->numerify('#########'),
            'phone' => null,
            'email' => mb_strtolower(fake()->safeEmail()),
            'preferred_contact_method' => PreferredContactMethod::Phone,
            'intent' => CustomerIntent::Buy,
            'status' => CustomerStatus::Active,
            'preferred_property_types' => null,
            'budget_min' => null,
            'budget_max' => null,
            'desired_city' => null,
            'desired_district' => null,
            'min_area_sqm' => null,
            'max_area_sqm' => null,
            'min_bedrooms' => null,
            'converted_property_id' => null,
        ];
    }

    public function forAgency(Agency $agency, User $creator, User $agent): static
    {
        return $this
            ->state(fn (): array => [
                'agency_id' => $agency->getKey(),
                'assigned_agent_id' => $agent->getKey(),
                'created_by_user_id' => $creator->getKey(),
            ])
            ->afterMaking(fn (Customer $customer) => $customer->setRelation('agency', $agency));
    }

    public function renter(): static
    {
        return $this->state(fn (): array => ['intent' => CustomerIntent::Rent]);
    }
}
