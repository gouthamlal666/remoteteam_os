<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

#[Fillable(['organizer_id', 'project_id', 'title', 'agenda', 'starts_at', 'duration_minutes', 'meeting_link', 'status', 'notes'])]
class Meeting extends Model
{
    public const SCHEDULED = 'scheduled';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['response', 'attended'])->withTimestamps();
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function isLive(): bool
    {
        return $this->status === self::SCHEDULED && now()->between($this->starts_at, $this->endsAt());
    }

    public function isUpcoming(): bool
    {
        return $this->status === self::SCHEDULED && $this->starts_at->isFuture();
    }

    /** Status shown to people: live / upcoming / awaiting notes / completed / cancelled. */
    public function displayStatus(): array
    {
        return match (true) {
            $this->status === self::CANCELLED => ['Cancelled', 'bg-slate-100 text-slate-500 line-through'],
            $this->status === self::COMPLETED => ['Completed', 'bg-emerald-100 text-emerald-700'],
            $this->isLive() => ['Live now', 'bg-red-100 text-red-700'],
            $this->isUpcoming() => ['Upcoming', 'bg-sky-100 text-sky-700'],
            default => ['Ended', 'bg-amber-100 text-amber-800'],
        };
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('organizer_id', $user->id)
            ->orWhereHas('participants', fn (Builder $p) => $p->whereKey($user->id)));
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', self::SCHEDULED)
            ->where('starts_at', '>=', now()->subHours(3))
            ->orderBy('starts_at');
    }
}
