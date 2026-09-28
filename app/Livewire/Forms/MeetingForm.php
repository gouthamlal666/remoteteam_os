<?php

namespace App\Livewire\Forms;

use App\Models\Meeting;
use Livewire\Form;

class MeetingForm extends Form
{
    public ?Meeting $meeting = null;

    public string $title = '';

    public string $agenda = '';

    public string $date = '';

    public string $time = '10:00';

    public int $duration_minutes = 30;

    public string $meeting_link = '';

    public ?int $project_id = null;

    /** @var array<int|string> */
    public array $participant_ids = [];

    public function setMeeting(Meeting $meeting): void
    {
        $this->meeting = $meeting;
        $this->title = $meeting->title;
        $this->agenda = (string) $meeting->agenda;
        $this->date = $meeting->starts_at->toDateString();
        $this->time = $meeting->starts_at->format('H:i');
        $this->duration_minutes = $meeting->duration_minutes;
        $this->meeting_link = (string) $meeting->meeting_link;
        $this->project_id = $meeting->project_id;
        $this->participant_ids = $meeting->participants()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'meeting_link' => ['nullable', 'url:http,https', 'max:500'],
            'project_id' => ['nullable', function ($attribute, $value, $fail) {
                if ($value && ! \App\Models\Project::visibleTo(auth()->user())->whereKey($value)->exists()) {
                    $fail('Choose one of your projects.');
                }
            }],
            'participant_ids' => ['array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    protected function messages(): array
    {
        return ['participant_ids.min' => 'Invite at least one person.'];
    }

    /** @return array{0: array<string, mixed>, 1: array<int>} */
    public function payload(): array
    {
        $data = $this->validate();

        return [[
            'title' => $data['title'],
            'agenda' => $data['agenda'] ?: null,
            'starts_at' => $data['date'].' '.$data['time'],
            'duration_minutes' => $data['duration_minutes'],
            'meeting_link' => $data['meeting_link'] ?: null,
            'project_id' => $data['project_id'] ?: null,
        ], $data['participant_ids']];
    }
}
