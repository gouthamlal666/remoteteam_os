<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['project_id', 'created_by', 'title', 'description', 'status', 'priority', 'start_date', 'deadline', 'estimated_hours', 'revision_count', 'submitted_at', 'completed_at'])]
class Task extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'start_date' => 'date',
            'deadline' => 'date',
            'estimated_hours' => 'decimal:2',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Overdue is derived rather than stored: any unfinished task whose
     * deadline has passed. This keeps it accurate without a scheduler.
     */
    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Completed
            && $this->deadline !== null
            && $this->deadline->lt(today());
    }

    public function completedOnTime(): bool
    {
        return $this->status === TaskStatus::Completed
            && ($this->deadline === null || $this->completed_at?->startOfDay()->lte($this->deadline));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees->contains('id', $user->id);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Completed->value)
            ->whereDate('deadline', '<', today());
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Completed->value);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', TaskStatus::Completed->value);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
    }

    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas('assignees', fn (Builder $q) => $q->whereKey($user->id));
    }
}
