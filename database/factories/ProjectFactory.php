<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'client_id' => null,
            'description' => fake()->paragraph(),
            'owner_id' => User::factory()->admin(),
            'status' => ProjectStatus::Active,
            'color' => 'indigo',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'deadline' => now()->addMonth()->toDateString(),
        ];
    }
}
