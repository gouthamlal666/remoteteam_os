<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'status' => 'active',
            'industry' => fake()->randomElement(['E-commerce', 'Education', 'Healthcare', 'Real estate', 'SaaS']),
            'website' => 'https://'.fake()->domainName(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+91 98### #####'),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'client_since' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
