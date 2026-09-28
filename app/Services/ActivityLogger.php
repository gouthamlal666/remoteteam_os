<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(?User $actor, string $action, string $description, ?Model $subject = null, array $properties = []): Activity
    {
        return Activity::create([
            'user_id' => $actor?->id,
            'project_id' => $this->projectIdFor($subject),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    private function projectIdFor(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Project => $subject->id,
            $subject instanceof Task => $subject->project_id,
            default => null,
        };
    }
}
