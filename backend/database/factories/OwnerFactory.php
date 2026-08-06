<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Owner\Enums\OwnerType;
use App\Models\Agency;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Owner> */
final class OwnerFactory extends Factory
{
    protected $model = Owner::class;

    public function definition(): array
    {
        return [
            'owner_type' => OwnerType::Person,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => null,
            'mobile' => '+989'.fake()->numerify('#########'),
            'phone' => null,
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'identity_number_encrypted' => null,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => 'Tehran',
            'province' => 'Tehran',
            'postal_code' => fake()->numerify('##########'),
            'notes' => null,
        ];
    }

    public function forAgency(Agency $agency, User $creator): static
    {
        return $this
            ->state(fn (): array => [
                'agency_id' => $agency->getKey(),
                'created_by_user_id' => $creator->getKey(),
            ])
            ->afterMaking(fn (Owner $owner) => $owner->setRelation('agency', $agency));
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'owner_type' => OwnerType::Company,
            'first_name' => null,
            'last_name' => null,
            'company_name' => fake()->company(),
        ]);
    }
}
