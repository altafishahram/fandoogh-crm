<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agency;
use App\Models\AgencySettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Agency> */
final class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'email' => mb_strtolower(fake()->unique()->companyEmail()),
            'phone' => '+9821'.fake()->numerify('########'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => 'Tehran',
            'province' => 'Tehran',
            'postal_code' => fake()->numerify('##########'),
            'country_code' => 'IR',
            'timezone' => 'Asia/Tehran',
            'locale' => 'fa-IR',
            'currency_code' => 'IRR',
            'is_active' => false,
            'activated_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Agency $agency): void {
            $settings = new AgencySettings([
                'property_code_prefix' => 'AG'.str_pad((string) $agency->getKey(), 4, '0', STR_PAD_LEFT),
                'next_property_sequence' => 1,
                'default_page_size' => 25,
            ]);
            $settings->agency()->associate($agency);
            $settings->save();
        });
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
