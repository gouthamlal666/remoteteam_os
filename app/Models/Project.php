<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'client', 'description', 'department_id', 'owner_id', 'status', 'color', 'start_date', 'deadline'])]
class Project extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Super admins and admins see every project; members see projects they
     * own or belong to.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id));

        });
    }

    /** Progress is the share of tasks completed; requires tasks_count/completed_tasks_count or loads them. */
    public function progress(): int
    {
        $total = $this->tasks_count ?? $this->tasks()->count();
        $done = $this->completed_tasks_count ?? $this->tasks()->where('status', TaskStatus::Completed)->count();

        return $total ? (int) round($done / $total * 100) : 0;
    }

    public function scopeWithProgressCounts(Builder $query): void
    {
        $query->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
        ]);
    }
}
