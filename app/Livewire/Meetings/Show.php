<?php

namespace App\Livewire\Meetings;

use App\Livewire\Forms\MeetingForm;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Meeting $meeting;

    public MeetingForm $form;

    public bool $showForm = false;

    public string $notes = '';

    /** @var array<int, bool> user id => attended */
    public array $attendance = [];

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view', $meeting);
        $this->fillWrapUp();
    }

    private function fillWrapUp(): void
    {
        $this->notes = (string) $this->meeting->notes;
        $this->attendance = $this->meeting->participants()->get()
            ->mapWithKeys(fn (User $p) => [$p->id => (bool) ($p->pivot->attended ?? $p->pivot->response === 'accepted')])
            ->all();
    }

    public function respond(string $response, MeetingService $meetings): void
    {
        $this->authorize('respond', $this->meeting);
        abort_unless(in_array($response, ['accepted', 'declined'], true), 422);

        $meetings->respond(auth()->user(), $this->meeting, $response);
        $this->dispatch('notify', message: $response === 'accepted' ? 'See you there!' : 'Invitation declined.');
    }

    public function edit(): void
    {
        $this->authorize('update', $this->meeting);
        $this->form->setMeeting($this->meeting);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function addDepartment(int $departmentId): void
    {
        $ids = User::active()->where('department_id', $departmentId)->pluck('id')->map(fn ($id) => (string) $id);
        $this->form->participant_ids = collect($this->form->participant_ids)->merge($ids)->unique()->values()->all();
    }

    public function save(MeetingService $meetings): void
    {
        $this->authorize('update', $this->meeting);
        [$data, $participants] = $this->form->payload();
        $this->meeting = $meetings->save(auth()->user(), $this->meeting, $data, $participants);
        $this->fillWrapUp();
        $this->showForm = false;
        $this->dispatch('notify', message: 'Meeting updated.');
    }

    public function removeParticipant(int $userId): void
    {
        $this->authorize('update', $this->meeting);
        abort_if($userId === $this->meeting->organizer_id, 422, 'The organizer cannot be removed.');

        $this->meeting->participants()->detach($userId);
        unset($this->attendance[$userId]);
        $this->dispatch('notify', message: 'Participant removed.');
    }

    public function saveNotes(): void
    {
        $this->authorize('update', $this->meeting);
        $this->validate(['notes' => ['nullable', 'string', 'max:20000']]);
        $this->meeting->update(['notes' => $this->notes ?: null]);
        $this->dispatch('notify', message: 'Notes saved.');
    }

    public function complete(MeetingService $meetings): void
    {
        $this->authorize('update', $this->meeting);
        $this->validate(['notes' => ['nullable', 'string', 'max:20000']]);
        $meetings->complete(auth()->user(), $this->meeting, $this->notes ?: null, $this->attendance);
        $this->dispatch('notify', message: 'Meeting completed and attendance saved.');
    }

    public function cancel(MeetingService $meetings): void
    {
        $this->authorize('update', $this->meeting);
        $meetings->cancel(auth()->user(), $this->meeting);
        $this->dispatch('notify', message: 'Meeting cancelled.');
    }

    public function delete(MeetingService $meetings)
    {
        $this->authorize('delete', $this->meeting);
        $meetings->delete(auth()->user(), $this->meeting);
        session()->flash('status', 'Meeting deleted.');

        return $this->redirectRoute('meetings.index', navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $this->meeting->load(['organizer', 'project', 'participants']);
        $canManage = $user->can('update', $this->meeting);

        return view('livewire.meetings.show', [
            'user' => $user,
            'canManage' => $canManage,
            'me' => $this->meeting->participants->firstWhere('id', $user->id),
            'people' => $canManage ? User::active()->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'departments' => $canManage ? Department::orderBy('name')->get(['id', 'name']) : collect(),
            'projects' => $canManage ? Project::visibleTo($user)->orderBy('name')->get(['id', 'name']) : collect(),
        ])->title($this->meeting->title);
    }
}
