<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientLink>
 */
class ClientLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => 'website',
            'label' => 'Main website',
            'url' => 'https://'.fake()->domainName(),
        ];
    }
}
