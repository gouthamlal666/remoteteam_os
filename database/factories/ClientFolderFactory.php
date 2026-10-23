<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientFolder>
 */
class ClientFolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->randomElement(ClientFolder::SUGGESTED),
        ];
    }
}
