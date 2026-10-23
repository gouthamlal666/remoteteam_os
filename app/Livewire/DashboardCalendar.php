<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Live month calendar for the dashboard: meetings and task deadlines the
 * viewer can see, with a day agenda. Refreshes itself every minute.
 */
class DashboardCalendar extends Component
{
    /** Month being shown, "YYYY-MM". */
    public string $month = '';

    /** Selected day, "YYYY-MM-DD". */
    public string $selected = '';

    public function mount(): void
    {
        $this->month = today()->format('Y-m');
        $this->selected = today()->toDateString();
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart()->addMonths($by)->format('Y-m');
    }

    public function goToday(): void
    {
        $this->mount();
    }

    public function select(string $date): void
    {
        $day = rescue(fn () => Carbon::parse($date), today(), false);
        $this->selected = $day->toDateString();

        // Clicking a greyed-out day from the next/previous month jumps to it.
        $this->month = $day->format('Y-m');
    }

    private function monthStart(): Carbon
    {
        return rescue(fn () => Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth(), today()->startOfMonth(), false);
    }

    /** @return array{meetings: Collection, tasks: Collection} */
    private function eventsBetween(Carbon $from, Carbon $to): array
    {
        $user = auth()->user();

        $meetings = Meeting::visibleTo($user)
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at', 'duration_minutes', 'status', 'meeting_link']);

        // Members see their own deadlines; managers see everything they oversee.
        $tasks = ($user->isMember() ? Task::assignedTo($user) : Task::visibleTo($user))
            ->with('project:id,name,color')
            ->whereBetween('deadline', [$from->toDateString(), $to->toDateString()])
            ->orderByRaw("case status when 'completed' then 1 else 0 end")
            ->get(['id', 'title', 'deadline', 'status', 'priority', 'project_id', 'completed_at']);

        return ['meetings' => $meetings, 'tasks' => $tasks];
    }

    public function render()
    {
        $start = $this->monthStart();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $events = $this->eventsBetween($gridStart, $gridEnd);

        $meetingsByDay = $events['meetings']->groupBy(fn (Meeting $m) => $m->starts_at->toDateString());
        $tasksByDay = $events['tasks']->groupBy(fn (Task $t) => $t->deadline->toDateString());
        $selected = Carbon::parse($this->selected);

        return view('livewire.dashboard-calendar', [
            'monthStart' => $start,
            'days' => collect(range(0, $gridStart->diffInDays($gridEnd)))->map(fn ($i) => $gridStart->copy()->addDays($i)),
            'meetingsByDay' => $meetingsByDay,
            'tasksByDay' => $tasksByDay,
            'selectedDay' => $selected,
            'dayMeetings' => $meetingsByDay->get($selected->toDateString(), collect()),
            'dayTasks' => $tasksByDay->get($selected->toDateString(), collect()),
        ]);
    }
}
