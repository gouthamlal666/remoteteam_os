<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\DailyReport;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    /** mine | team | weekly | performance */
    #[Url]
    public string $tab = 'mine';

    // Daily report form
    public string $reportDate = '';

    public string $completed = '';

    public string $inProgress = '';

    public string $blockers = '';

    public string $hoursWorked = '';

    // Manager filters
    #[Url(except: '')]
    public string $teamDate = '';

    #[Url(except: '')]
    public string $week = '';

    #[Url(except: '')]
    public string $department = '';

    public function mount(): void
    {
        if (! auth()->user()->isManager() && $this->tab !== 'mine') {
            $this->tab = 'mine';
        }

        $this->teamDate = $this->teamDate ?: today()->toDateString();
        $this->loadReport(today()->toDateString());
    }

    public function updatedReportDate(string $value): void
    {
        $this->loadReport($value);
    }

    public function editReport(string $date): void
    {
        $this->tab = 'mine';
        $this->loadReport($date);
    }

    private function loadReport(string $date): void
    {
        $date = rescue(fn () => Carbon::parse($date), today(), false);
        if ($date->isFuture()) {
            $date = today();
        }

        $this->reportDate = $date->toDateString();
        $report = auth()->user()->dailyReports()->whereDate('report_date', $date)->first();

        $this->completed = (string) $report?->completed;
        $this->inProgress = (string) $report?->in_progress;
        $this->blockers = (string) $report?->blockers;
        $this->hoursWorked = $report ? rtrim(rtrim($report->hours_worked, '0'), '.') : '';
        $this->resetValidation();
    }

    public function submitReport(): void
    {
        $data = $this->validate([
            'reportDate' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(14)->toDateString()],
            'completed' => ['required', 'string', 'max:5000'],
            'inProgress' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'hoursWorked' => ['required', 'numeric', 'min:0', 'max:24'],
        ], ['reportDate.after_or_equal' => 'Reports can only be filed for the last 14 days.']);

        $user = auth()->user();
        $report = $user->dailyReports()->whereDate('report_date', $data['reportDate'])->first()
            ?? $user->dailyReports()->make(['report_date' => $data['reportDate']]);

        $report->fill([
            'completed' => $data['completed'],
            'in_progress' => $data['inProgress'] ?: null,
            'blockers' => $data['blockers'] ?: null,
            'hours_worked' => $data['hoursWorked'],
        ])->save();

        $this->dispatch('notify', message: 'Daily report saved.');
    }

    /** You can delete your own reports; super admins can delete anyone's. */
    public function deleteReport(int $reportId): void
    {
        $report = DailyReport::findOrFail($reportId);
        abort_unless($report->user_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);
        $report->delete();

        if ($report->user_id === auth()->id() && $report->report_date->toDateString() === $this->reportDate) {
            $this->loadReport($this->reportDate);
        }

        $this->dispatch('notify', message: 'Report deleted.');
    }

    /** People the viewer manages, optionally narrowed to a department. */
    private function team(User $user): Collection
    {
        return User::manageableBy($user)->active()
            ->where('role', '!=', Role::SuperAdmin->value)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->with('department:id,name')
            ->orderBy('name')->get();
    }

    private function weekStart(): Carbon
    {
        return rescue(fn () => Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY), today()->startOfWeek(Carbon::MONDAY), false);
    }

    public function shiftWeek(int $by): void
    {
        $this->week = $this->weekStart()->addWeeks($by)->toDateString();
    }

    public function render(PerformanceService $performance)
    {
        $user = auth()->user();

        if (! $user->isManager()) {
            $this->tab = 'mine';
        }

        $data = [
            'user' => $user,
            'myReports' => $user->dailyReports()->latest('report_date')->limit(14)->get(),
            'departments' => $user->isManager() ? Department::orderBy('name')->get(['id', 'name']) : collect(),
        ];

        if ($user->isManager()) {
            match ($this->tab) {
                'team' => $data += $this->teamData($user),
                'weekly' => $data += [
                    'weekStart' => $this->weekStart(),
                    'rows' => $performance->weekly($this->team($user), $this->weekStart()),
                ],
                'performance' => $data += [
                    'people' => $this->team($user)->map(fn (User $p) => ['user' => $p] + $p->taskStats())->sortByDesc('completion_rate')->values(),
                    'trend' => $performance->monthlyTrend(Task::visibleTo($user)),
                ],
                default => null,
            };
        }

        return view('livewire.reports.index', $data);
    }

    private function teamData(User $user): array
    {
        $date = rescue(fn () => Carbon::parse($this->teamDate), today(), false);
        $team = $this->team($user);
        $reports = DailyReport::whereIn('user_id', $team->pluck('id'))->whereDate('report_date', $date)->get()->keyBy('user_id');

        return ['teamDay' => $date, 'team' => $team, 'teamReports' => $reports];
    }
}
