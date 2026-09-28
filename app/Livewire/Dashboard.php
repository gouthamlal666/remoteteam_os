<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(PerformanceService $performance)
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'user' => $user,
            'greeting' => $this->greeting(),
            'stats' => $user->isMember() ? $this->memberStats($user) : $this->managerStats($user),
            'departments' => match (true) {
                $user->isSuperAdmin() => $performance->departmentBreakdown(),
                $user->isAdmin() => $performance->departmentBreakdown()
                    ->filter(fn ($row) => in_array($row['department']->id, $user->departmentScopeIds(), true))->values(),
                default => collect(),
            },
            'focusTasks' => $this->focusTasks($user),
            'projects' => Project::visibleTo($user)->withProgressCounts()->where('status', '!=', 'completed')
                ->orderByRaw('deadline is null, deadline')->limit(5)->get(),
            'meetings' => Meeting::visibleTo($user)->upcoming()->with('participants')->limit(4)->get(),
            'reportedToday' => $user->dailyReports()->whereDate('report_date', today())->exists(),
        ]);
    }

    private function managerStats(User $user): array
    {
        $tasks = fn (): Builder => Task::visibleTo($user);

        $stats = [
            'members' => User::manageableBy($user)->role(Role::Member)->active()->count(),
            'projects' => Project::visibleTo($user)->count(),
            'tasks' => $tasks()->count(),
            'completed' => $tasks()->completed()->count(),
            'pending' => $tasks()->pending()->count(),
            'overdue' => $tasks()->overdue()->count(),
        ];

        if ($user->isSuperAdmin()) {
            $stats['admins'] = User::role(Role::Admin)->active()->count();
            $stats['departments'] = Department::count();
        }

        return $stats;
    }

    private function memberStats(User $user): array
    {
        return $user->taskStats() + ['projects' => $user->projects()->count()];
    }

    /** Tasks needing attention: overdue or due within 3 days (managers also see items awaiting review). */
    private function focusTasks(User $user)
    {
        $query = $user->isMember() ? Task::assignedTo($user) : Task::visibleTo($user);

        return $query->with(['project', 'assignees'])
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $d) => $d->pending()->whereDate('deadline', '<=', today()->addDays(3)))
                ->when($user->isManager(), fn (Builder $r) => $r->orWhere('status', 'review')))
            ->orderByRaw('deadline is null, deadline')
            ->limit(8)
            ->get();
    }

    private function greeting(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
