<?php

namespace App\Livewire\Meetings;

use App\Livewire\Forms\MeetingForm;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Meetings')]
class Index extends Component
{
    public MeetingForm $form;

    public bool $showForm = false;

    /** upcoming | calendar | history */
    #[Url]
    public string $view = 'upcoming';

    /** Calendar month, "YYYY-MM". */
    #[Url(except: '')]
    public string $month = '';

    public function create(?string $date = null): void
    {
        $this->authorize('create', Meeting::class);
        $this->form->reset();
        $this->form->date = $date ?: today()->toDateString();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function addDepartment(int $departmentId): void
    {
        $ids = User::active()->where('department_id', $departmentId)->pluck('id')->map(fn ($id) => (string) $id);
        $this->form->participant_ids = collect($this->form->participant_ids)->merge($ids)->unique()->values()->all();
    }

    public function save(MeetingService $meetings)
    {
        $this->authorize('create', Meeting::class);
        [$data, $participants] = $this->form->payload();
        $meeting = $meetings->save(auth()->user(), null, $data, $participants);

        return $this->redirectRoute('meetings.show', $meeting, navigate: true);
    }

    public function deleteMeeting(int $meetingId, MeetingService $meetings): void
    {
        $meeting = Meeting::findOrFail($meetingId);
        $this->authorize('delete', $meeting);
        $meetings->delete(auth()->user(), $meeting);
        $this->dispatch('notify', message: "Deleted \"{$meeting->title}\".");
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart()->addMonths($by)->format('Y-m');
    }

    private function monthStart(): Carbon
    {
        return rescue(fn () => Carbon::createFromFormat('Y-m', $this->month)->startOfMonth(), today()->startOfMonth(), false);
    }

    public function render()
    {
        $user = auth()->user();
        $base = fn () => Meeting::visibleTo($user)->with(['organizer', 'participants', 'project']);

        $data = ['user' => $user];

        if ($this->view === 'calendar') {
            $start = $this->monthStart();
            $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

            $data['monthStart'] = $start;
            $data['days'] = collect(range(0, $gridStart->diffInDays($gridEnd)))->map(fn ($i) => $gridStart->copy()->addDays($i));
            $data['byDay'] = $base()->whereBetween('starts_at', [$gridStart, $gridEnd->copy()->endOfDay()])
                ->orderBy('starts_at')->get()->groupBy(fn (Meeting $m) => $m->starts_at->toDateString());
        } elseif ($this->view === 'history') {
            $data['meetings'] = $base()->where(fn ($q) => $q->where('starts_at', '<', now()->subHours(3))->orWhere('status', '!=', Meeting::SCHEDULED))
                ->latest('starts_at')->limit(50)->get();
        } else {
            $data['meetings'] = $base()->upcoming()->get();
        }

        if ($user->isManager()) {
            $data['people'] = User::active()->orderBy('name')->get(['id', 'name', 'job_title']);
            $data['departments'] = Department::orderBy('name')->get(['id', 'name']);
            $data['projects'] = Project::visibleTo($user)->orderBy('name')->get(['id', 'name']);
        }

        return view('livewire.meetings.index', $data);
    }
}
