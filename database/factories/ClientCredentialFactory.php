<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientCredential>
 */
class ClientCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'label' => 'WordPress admin',
            'url' => 'https://'.fake()->domainName().'/wp-admin',
            'username' => fake()->userName(),
            'password' => fake()->password(12, 16),
        ];
    }
}
