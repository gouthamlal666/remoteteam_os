<?php

namespace App\Livewire\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Create / edit task modal. Open it from anywhere on the page with:
 *   $dispatch('open-task-editor', { projectId: 1 })   // new task
 *   $dispatch('open-task-editor', { taskId: 5 })      // edit
 * Emits `task-saved` so the host page can refresh.
 */
class TaskEditor extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?int $taskId = null;

    public ?int $project_id = null;

    public string $title = '';

    public string $description = '';

    /** @var array<int|string> */
    public array $assignee_ids = [];

    public string $priority = 'medium';

    public string $status = 'todo';

    public ?string $start_date = null;

    public ?string $deadline = null;

    public ?string $estimated_hours = null;

    /** @var array<TemporaryUploadedFile> */
    public array $files = [];

    #[On('open-task-editor')]
    public function openEditor(?int $projectId = null, ?int $taskId = null): void
    {
        $this->reset();
        $this->resetValidation();

        if ($taskId) {
            $task = Task::with('assignees')->findOrFail($taskId);
            $this->authorize('update', $task);

            $this->taskId = $task->id;
            $this->project_id = $task->project_id;
            $this->title = $task->title;
            $this->description = (string) $task->description;
            $this->assignee_ids = $task->assignees->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->priority = $task->priority->value;
            $this->status = $task->status->value;
            $this->start_date = $task->start_date?->toDateString();
            $this->deadline = $task->deadline?->toDateString();
            $this->estimated_hours = $task->estimated_hours;
        } else {
            $this->authorize('create', Task::class);
            $this->project_id = $projectId;
            $this->start_date = today()->toDateString();
        }

        $this->open = true;
    }

    public function save(TaskService $tasks): void
    {
        $user = auth()->user();

        $data = $this->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'assignee_ids' => ['array'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['required', new Enum(TaskPriority::class)],
            'status' => ['required', new Enum(TaskStatus::class)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $this->authorize('create', [Task::class, $project]);

        $assignees = $data['assignee_ids'];
        $fields = collect($data)->except(['assignee_ids', 'files'])->map(fn ($v) => $v === '' ? null : $v)->all();

        if ($this->taskId) {
            $task = Task::findOrFail($this->taskId);
            $this->authorize('update', $task);

            $newStatus = TaskStatus::from($fields['status']);
            unset($fields['status']);
            $task = $tasks->update($user, $task, $fields, $assignees);
            $tasks->changeStatus($user, $task, $newStatus);
        } else {
            $task = $tasks->create($user, $fields, $assignees);
        }

        foreach ($this->files as $file) {
            $tasks->attach($user, $task, $file);
        }

        $this->open = false;
        $this->dispatch('task-saved', taskId: $task->id);
        $this->dispatch('notify', message: $this->taskId ? 'Task updated.' : 'Task created.');
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function render()
    {
        $user = auth()->user();

        $projects = $user->isManager()
            ? Project::visibleTo($user)->orderBy('name')->get(['id', 'name', 'owner_id', 'department_id'])->filter(fn ($p) => $user->can('update', $p))
            : collect();

        // Suggest project members first, then everyone else who is active.
        $memberIds = $this->project_id
            ? Project::find($this->project_id)?->members()->pluck('users.id')->all() ?? []
            : [];

        $people = User::active()->orderBy('name')->get(['id', 'name', 'job_title'])
            ->sortBy(fn ($p) => in_array($p->id, $memberIds) ? 0 : 1)->values();

        return view('livewire.tasks.task-editor', [
            'projects' => $projects,
            'people' => $people,
            'memberIds' => $memberIds,
        ]);
    }
}
