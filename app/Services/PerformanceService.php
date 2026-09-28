<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\DailyReport;
use App\Models\Department;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Performance is computed only from transparent task data (assigned,
 * completed, deadlines) — never from activity tracking or surveillance.
 */
class PerformanceService
{
    /**
     * Completion and on-time rates per department, based on tasks in each
     * department's projects.
     *
     * @return Collection<int, array{department: Department, total: int, completed: int, overdue: int, completion_rate: int, on_time_rate: int}>
     */
    public function departmentBreakdown(): Collection
    {
        $tasks = Task::query()
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->whereNotNull('projects.department_id')
            ->get(['tasks.id', 'tasks.status', 'tasks.deadline', 'tasks.completed_at', 'projects.department_id'])
            ->groupBy('department_id');

        return Department::orderBy('name')->get()->map(function (Department $department) use ($tasks) {
            $group = $tasks->get($department->id, collect());
            $completed = $group->where('status', TaskStatus::Completed);

            return [
                'department' => $department,
                'total' => $group->count(),
                'completed' => $completed->count(),
                'overdue' => $group->filter->isOverdue()->count(),
                'completion_rate' => $group->count() ? (int) round($completed->count() / $group->count() * 100) : 0,
                'on_time_rate' => $completed->count()
                    ? (int) round($completed->filter->completedOnTime()->count() / $completed->count() * 100)
                    : 0,
            ];
        });
    }

    /**
     * One row per person for the week starting $weekStart (Monday).
     *
     * @param  Collection<int, User>  $people
     */
    public function weekly(Collection $people, Carbon $weekStart): Collection
    {
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $ids = $people->pluck('id');

        $reports = DailyReport::whereIn('user_id', $ids)
            ->whereBetween('report_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('report_date')->get()->groupBy('user_id');

        $tasks = Task::query()
            ->join('task_assignees as ta', 'ta.task_id', '=', 'tasks.id')
            ->whereIn('ta.user_id', $ids)
            ->where(fn (Builder $q) => $q
                ->whereBetween('tasks.completed_at', [$weekStart, $weekEnd->copy()->endOfDay()])
                ->orWhere(fn (Builder $o) => $o->overdue()))
            ->get(['tasks.id', 'tasks.title', 'tasks.status', 'tasks.deadline', 'tasks.completed_at', 'ta.user_id'])
            ->groupBy('user_id');

        $tracked = TimeEntry::whereIn('user_id', $ids)->whereNotNull('ended_at')
            ->whereBetween('started_at', [$weekStart, $weekEnd->copy()->endOfDay()])
            ->selectRaw('user_id, sum(minutes) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return $people->map(function (User $person) use ($reports, $tasks, $weekStart, $weekEnd, $tracked) {
            $mine = $reports->get($person->id, collect());
            $myTasks = $tasks->get($person->id, collect());
            $completed = $myTasks->filter(fn (Task $t) => $t->completed_at?->between($weekStart, $weekEnd->copy()->endOfDay()));

            return [
                'user' => $person,
                'reports' => $mine,
                'days_reported' => $mine->count(),
                'hours' => (float) $mine->sum('hours_worked'),
                'tracked_minutes' => (int) ($tracked[$person->id] ?? 0),
                'completed' => $completed,
                'completed_on_time' => $completed->filter->completedOnTime()->count(),
                'overdue' => $myTasks->filter->isOverdue(),
                'blockers' => $mine->filter(fn ($r) => filled($r->blockers)),
            ];
        });
    }

    /**
     * Assigned / completed / late counts per month for the last $months months,
     * over the tasks in $tasks (already scoped to what the viewer may see).
     *
     * @return Collection<string, array{label: string, created: int, done: int, late: int}>
     */
    public function monthlyTrend(Builder $tasks, int $months = 6): Collection
    {
        $from = now()->startOfMonth()->subMonths($months - 1);

        $rows = (clone $tasks)
            ->where(fn (Builder $q) => $q->where('tasks.created_at', '>=', $from)->orWhere('tasks.completed_at', '>=', $from))
            ->get(['tasks.id', 'tasks.status', 'tasks.deadline', 'tasks.created_at', 'tasks.completed_at']);

        return collect(range(0, $months - 1))->mapWithKeys(function ($i) use ($from, $rows) {
            $start = $from->copy()->addMonths($i);
            $end = $start->copy()->endOfMonth();
            $done = $rows->filter(fn (Task $t) => $t->completed_at?->between($start, $end));

            return [$start->format('Y-m') => [
                'label' => $start->format('M Y'),
                'created' => $rows->filter(fn (Task $t) => $t->created_at->between($start, $end))->count(),
                'done' => $done->count(),
                'late' => $done->reject->completedOnTime()->count(),
            ]];
        });
    }

    /**
     * Share of a person's completed tasks per project.
     *
     * @return Collection<int, array{project: string, count: int, share: int}>
     */
    public function projectContribution(User $user): Collection
    {
        $done = $user->tasks()->completed()->with('project:id,name')->get(['tasks.id', 'tasks.project_id']);
        $total = $done->count();

        return $done->groupBy('project_id')
            ->map(fn ($group) => [
                'project' => $group->first()->project->name,
                'count' => $group->count(),
                'share' => $total ? (int) round($group->count() / $total * 100) : 0,
            ])
            ->sortByDesc('count')->values();
    }
}
