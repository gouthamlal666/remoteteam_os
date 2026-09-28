<?php

namespace App\Livewire\Forms;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Form;

class ProjectForm extends Form
{
    public const COLORS = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'violet'];

    public ?Project $project = null;

    public string $name = '';

    public string $client = '';

    public string $description = '';

    public ?int $department_id = null;

    public string $status = 'active';

    public string $color = 'indigo';

    public ?string $start_date = null;

    public ?string $deadline = null;

    /** @var array<int> */
    public array $member_ids = [];

    public function setProject(Project $project): void
    {
        $this->project = $project;
        $this->fill($project->only(['name', 'department_id', 'color']));
        $this->client = (string) $project->client;
        $this->description = (string) $project->description;
        $this->status = $project->status->value;
        $this->start_date = $project->start_date?->toDateString();
        $this->deadline = $project->deadline?->toDateString();
        $this->member_ids = $project->members()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'client' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status' => ['required', new Enum(ProjectStatus::class)],
            'color' => ['required', Rule::in(self::COLORS)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function save(User $actor, ActivityLogger $activity): Project
    {
        $data = $this->validate();
        $members = array_map('intval', $data['member_ids']);
        unset($data['member_ids']);

        $data['start_date'] = $data['start_date'] ?: null;
        $data['deadline'] = $data['deadline'] ?: null;

        return DB::transaction(function () use ($actor, $activity, $data, $members) {
            if ($this->project) {
                $this->project->update($data);
                $activity->log($actor, 'project.updated', "updated project {$this->project->name}", $this->project);
            } else {
                $this->project = Project::create($data + ['owner_id' => $actor->id]);
                $activity->log($actor, 'project.created', "created project {$this->project->name}", $this->project);
            }

            $this->project->members()->sync($members);

            return $this->project;
        });
    }
}
