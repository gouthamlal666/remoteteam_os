<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientFile>
 */
class ClientFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'type' => 'contract',
            'title' => 'Service agreement',
            'path' => 'clients/1/agreement.pdf',
            'original_name' => 'agreement.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
