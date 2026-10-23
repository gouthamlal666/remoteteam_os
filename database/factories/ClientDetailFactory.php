<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDetail>
 */
class ClientDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'label' => 'Hosting provider',
            'value' => 'Hostinger',
        ];
    }
}
