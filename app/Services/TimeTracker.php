<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Self-reported time tracking: people start/stop a timer or log time by
 * hand. There is no screenshotting or activity monitoring.
 */
class TimeTracker
{
    /** Starting a timer stops whatever was running before. */
    public function start(User $user, Task $task): TimeEntry
    {
        return DB::transaction(function () use ($user, $task) {
            $this->stop($user);

            return $user->timeEntries()->create(['task_id' => $task->id, 'started_at' => now()]);
        });
    }

    public function stop(User $user): ?TimeEntry
    {
        $entry = $user->timeEntries()->running()->first();

        if ($entry) {
            // Nearest minute, but at least one so short sessions still count.
            $entry->update([
                'ended_at' => now(),
                'minutes' => max(1, (int) round($entry->started_at->diffInSeconds(now(), true) / 60)),
            ]);
        }

        return $entry;
    }

    public function log(User $user, Task $task, int $minutes, Carbon $date, ?string $note = null): TimeEntry
    {
        $start = $date->copy()->setTime(9, 0);

        return $user->timeEntries()->create([
            'task_id' => $task->id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes($minutes),
            'minutes' => $minutes,
            'note' => $note,
        ]);
    }
}
