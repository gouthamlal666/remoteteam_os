<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'project_id', 'subject_type', 'subject_id', 'action', 'description', 'properties', 'created_at'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Activity is visible if it belongs to a visible project, or is the viewer's own. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->whereHas('project', fn (Builder $p) => $p->visibleTo($user))
            ->orWhere('user_id', $user->id));
    }
}
