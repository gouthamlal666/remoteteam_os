<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => fn (array $attrs) => Project::find($attrs['project_id'])->owner_id,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
            'estimated_hours' => fake()->randomElement([2, 4, 6, 8, 12]),
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['deadline' => now()->subDays(3)->toDateString()]);
    }
}
