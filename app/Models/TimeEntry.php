<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'task_id', 'started_at', 'ended_at', 'minutes', 'note'])]
class TimeEntry extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'Deleted user']);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Minutes including the live portion of a running timer. */
    public function currentMinutes(): int
    {
        return $this->isRunning() ? (int) $this->started_at->diffInMinutes(now(), true) : $this->minutes;
    }

    public function scopeRunning(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public static function format(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? ($m ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }
}
