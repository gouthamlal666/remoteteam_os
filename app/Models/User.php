<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TaskStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'job_title', 'phone', 'skills', 'bio', 'is_active', 'timezone', 'work_start', 'work_end', 'status_emoji', 'status_text', 'status_expires_at', 'email_notifications', 'whatsapp_notifications', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'skills' => 'array',
            'is_active' => 'boolean',
            'status_expires_at' => 'datetime',
            'email_notifications' => 'boolean',
            'whatsapp_notifications' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')->withTimestamps();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')
            ->withPivot('last_read_at')->withTimestamps();
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_participants')
            ->withPivot(['response', 'attended'])->withTimestamps();
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    /**
     * How complete someone's employee record is. Expects `employeeProfile` and
     * `employee_documents_count` to be loaded for lists (falls back to queries).
     *
     * @return array{done: int, total: int, percent: int, missing: array<string>}
     */
    public function recordCompleteness(): array
    {
        $p = $this->employeeProfile;
        $checks = [
            'Photo' => (bool) $this->avatar_path,
            'Mobile' => filled($this->phone),
            'Employee ID' => filled($p?->employee_code),
            'Joining date' => (bool) $p?->date_of_joining,
            'Date of birth' => (bool) $p?->date_of_birth,
            'Address' => filled($p?->address_line) || filled($p?->city),
            'Guardian name' => filled($p?->guardian_name),
            'Guardian phone' => filled($p?->guardian_phone),
            'Documents' => ($this->employee_documents_count ?? $this->employeeDocuments()->count()) > 0,
        ];
        $done = count(array_filter($checks));

        return [
            'done' => $done,
            'total' => count($checks),
            'percent' => (int) round($done / count($checks) * 100),
            'missing' => array_keys(array_filter($checks, fn ($ok) => ! $ok)),
        ];
    }

    /** Photo URL (served through an authenticated route), or null to show initials. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path && $this->exists
            ? route('avatars.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
            : null;
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function runningTimer(): ?TimeEntry
    {
        return $this->timeEntries()->running()->with('task:id,title')->first();
    }

    /** Seen within the last 5 minutes (updated by EnsureUserIsActive on every request). */
    public function isOnline(): bool
    {
        return $this->last_seen_at?->gt(now()->subMinutes(5)) ?? false;
    }

    public function localTime(): Carbon
    {
        return now()->setTimezone($this->timezone ?: config('app.timezone'));
    }

    /** Whether it's currently inside this person's working hours (their timezone). */
    public function isWorkingNow(): ?bool
    {
        if (! $this->work_start || ! $this->work_end) {
            return null;
        }

        $now = $this->localTime()->format('H:i:s');

        return $this->work_start <= $this->work_end
            ? $now >= $this->work_start && $now < $this->work_end
            : $now >= $this->work_start || $now < $this->work_end; // overnight shifts
    }

    /** Current custom status, or null if none / expired. */
    public function activeStatus(): ?string
    {
        if (! $this->status_text && ! $this->status_emoji) {
            return null;
        }

        if ($this->status_expires_at?->isPast()) {
            return null;
        }

        return trim(($this->status_emoji ?? '').' '.($this->status_text ?? ''));
    }

    /** Mention handle, e.g. "rahul.menon". */
    public function handle(): string
    {
        return Str::of($this->name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->toString();
    }

    /** Unread chat messages across all of this user's conversations. */
    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->join('conversation_members as cm', fn ($j) => $j
                ->on('cm.conversation_id', '=', 'messages.conversation_id')
                ->where('cm.user_id', $this->id))
            ->where('messages.user_id', '!=', $this->id)
            ->where(fn ($q) => $q->whereNull('cm.last_read_at')->orWhereColumn('messages.created_at', '>', 'cm.last_read_at'))
            ->count();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isMember(): bool
    {
        return $this->role === Role::Member;
    }

    /** Super admins and admins can manage projects, tasks and people. */
    public function isManager(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin();
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeRole(Builder $query, Role $role): void
    {
        $query->where('role', $role->value);
    }

    /**
     * Users that $viewer is allowed to see and manage in the people directory.
     * Super admins manage everyone; admins manage every member in any department
     * (but not other admins or super admins).
     */
    public function scopeManageableBy(Builder $query, User $viewer): void
    {
        if ($viewer->isSuperAdmin()) {
            return;
        }

        $query->where('role', Role::Member->value);
    }

    /** Transparent task-based stats for the profile / performance cards. */
    public function taskStats(): array
    {
        $tasks = $this->tasks()->get(['tasks.id', 'status', 'deadline', 'completed_at', 'revision_count']);

        $completed = $tasks->where('status', TaskStatus::Completed);
        $onTime = $completed->filter(fn (Task $t) => $t->completedOnTime());
        $overdue = $tasks->filter(fn (Task $t) => $t->isOverdue());

        return [
            'assigned' => $tasks->count(),
            'completed' => $completed->count(),
            'pending' => $tasks->count() - $completed->count(),
            'overdue' => $overdue->count(),
            'revisions' => (int) $tasks->sum('revision_count'),
            'completion_rate' => $tasks->count() ? (int) round($completed->count() / $tasks->count() * 100) : 0,
            'on_time_rate' => $completed->count() ? (int) round($onTime->count() / $completed->count() * 100) : 0,
            'meeting_attendance' => $this->meetingAttendanceRate(),
        ];
    }

    /** Share of past meetings (with attendance taken) that this user attended; null if none yet. */
    public function meetingAttendanceRate(): ?int
    {
        $taken = $this->meetings()->where('meetings.status', '!=', Meeting::CANCELLED)
            ->whereNotNull('meeting_participants.attended')->get(['meetings.id']);

        if ($taken->isEmpty()) {
            return null;
        }

        return (int) round($taken->filter(fn ($m) => $m->pivot->attended)->count() / $taken->count() * 100);
    }
}
