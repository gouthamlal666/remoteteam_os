<?php

namespace App\Livewire\Projects;

use App\Enums\TaskStatus;
use App\Livewire\Forms\ProjectForm;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TaskService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Project $project;

    public ProjectForm $form;

    public bool $showForm = false;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function edit(): void
    {
        $this->authorize('update', $this->project);
        $this->form->setProject($this->project);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveProject(ActivityLogger $activity): void
    {
        $this->authorize('update', $this->project);
        $this->project = $this->form->save(auth()->user(), $activity);
        $this->showForm = false;
        $this->dispatch('notify', message: 'Project updated.');
    }

    public function delete(ActivityLogger $activity)
    {
        $this->authorize('delete', $this->project);
        $activity->log(auth()->user(), 'project.deleted', "deleted project {$this->project->name}");
        $this->project->delete();

        session()->flash('status', 'Project deleted.');

        return $this->redirectRoute('projects.index', navigate: true);
    }

    /**
     * Drag-and-drop on the board (wire:sort). Managers can move a card to any
     * column; assignees can only move their own card between To Do and In
     * Progress — review, approval and revision go through the task page.
     */
    public function moveTask(int|string $taskId, int $position, string $status, TaskService $tasks): void
    {
        $task = $this->project->tasks()->with('assignees')->findOrFail((int) $taskId);
        $target = TaskStatus::tryFrom($status) ?? abort(422);
        $user = auth()->user();

        $allowed = $user->can('update', $task)
            || ($task->isAssignedTo($user)
                && in_array($task->status, TaskStatus::memberSettable(), true)
                && in_array($target, TaskStatus::memberSettable(), true));

        if (! $allowed) {
            $this->dispatch('notify', message: 'Use the task page to submit or review work.');

            return;
        }

        $tasks->changeStatus($user, $task, $target);
    }

    public function deleteTask(int $taskId, TaskService $tasks): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);
        $this->authorize('delete', $task);
        $tasks->delete(auth()->user(), $task);
        $this->dispatch('notify', message: "Deleted \"{$task->title}\".");
    }

    /** Removes someone from the project and unassigns them from its tasks. */
    public function removeMember(int $userId, ActivityLogger $activity): void
    {
        $this->authorize('update', $this->project);
        $member = $this->project->members()->findOrFail($userId);

        $this->project->members()->detach($member->id);
        foreach ($this->project->tasks as $task) {
            $task->assignees()->detach($member->id);
        }

        $activity->log(auth()->user(), 'project.member_removed', "removed {$member->name} from {$this->project->name}", $this->project);
        $this->dispatch('notify', message: "Removed {$member->name} from the project.");
    }

    #[On('task-saved')]
    public function refreshTasks(): void
    {
        $this->project->refresh();
    }

    public function render()
    {
        $user = auth()->user();
        $this->project->load(['owner', 'department', 'members.department']);

        $tasks = $this->project->tasks()->with('assignees')
            ->withCount(['checklistItems', 'checklistItems as checklist_done_count' => fn ($q) => $q->where('is_done', true)])
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByRaw('deadline is null, deadline')
            ->get();

        $canManage = $user->can('update', $this->project);

        return view('livewire.projects.show', [
            'columns' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $s) => [$s->value => [
                'status' => $s,
                'tasks' => $tasks->where('status', $s),
            ]]),
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', TaskStatus::Completed)->count(),
            'overdue' => $tasks->filter->isOverdue()->count(),
            'hours' => $tasks->sum('estimated_hours'),
            'activities' => $this->project->activities()->with(['user', 'project', 'subject'])->latest('created_at')->limit(10)->get(),
            'canManage' => $canManage,
            'departments' => $canManage ? Department::orderBy('name')->get(['id', 'name']) : collect(),
            'people' => $canManage ? User::active()->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'clients' => $canManage ? Project::visibleTo($user)->whereNotNull('client')->where('client', '!=', '')->distinct()->orderBy('client')->pluck('client') : collect(),
        ])->title($this->project->name);
    }
}
