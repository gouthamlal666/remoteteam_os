<?php

namespace App\Livewire\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tasks')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** A TaskStatus value, or "overdue" / "open". */
    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $assignee = '';

    /** Client (business) name, set from the Projects page. */
    #[Url(except: '')]
    public string $client = '';

    /** "mine" or "all" — members default to their own tasks. */
    #[Url]
    public string $scope = '';

    public function mount(): void
    {
        if ($this->scope === '') {
            $this->scope = auth()->user()->isMember() ? 'mine' : 'all';
        }
    }

    public function updating($property): void
    {
        if (! str_starts_with($property, 'paginators') && ! str_starts_with($property, 'selected')) {
            $this->resetPage();
        }
    }

    /** @var array<int|string> ids ticked for bulk delete */
    public array $selected = [];

    public function deleteTask(int $taskId, TaskService $tasks): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('delete', $task);
        $tasks->delete(auth()->user(), $task);
        $this->selected = array_values(array_diff($this->selected, [(string) $taskId, $taskId]));
        $this->dispatch('notify', message: "Deleted \"{$task->title}\".");
    }

    public function deleteSelected(TaskService $tasks): void
    {
        $user = auth()->user();
        $deleted = 0;

        foreach (Task::with('project', 'attachments')->whereKey($this->selected)->get() as $task) {
            if ($user->can('delete', $task)) {
                $tasks->delete($user, $task);
                $deleted++;
            }
        }

        $this->selected = [];
        $this->dispatch('notify', message: "Deleted {$deleted} ".str('task')->plural($deleted).'.');
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'priority', 'project', 'assignee', 'client');
    }

    #[On('task-saved')]
    public function refresh(): void {}

    public function render()
    {
        $user = auth()->user();

        $tasks = Task::visibleTo($user)
            ->with(['project', 'assignees'])
            ->when($this->scope === 'mine', fn ($q) => $q->assignedTo($user))
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->status === 'overdue', fn ($q) => $q->overdue())
            ->when($this->status === 'open', fn ($q) => $q->pending())
            ->when($this->status && ! in_array($this->status, ['overdue', 'open']), fn ($q) => $q->where('status', $this->status))
            ->when($this->priority, fn ($q) => $q->where('priority', $this->priority))
            ->when($this->project, fn ($q) => $q->where('project_id', $this->project))
            ->when($this->assignee, fn ($q) => $q->whereHas('assignees', fn ($a) => $a->whereKey($this->assignee)))
            ->when($this->client, fn ($q) => $q->whereHas('project', fn ($p) => \App\Livewire\Projects\Index::applyClientFilter($p, $this->client)))
            ->orderByRaw("case status when 'completed' then 1 else 0 end")
            ->orderByRaw('deadline is null, deadline')
            ->latest('id')
            ->paginate(20);

        return view('livewire.tasks.index', [
            'tasks' => $tasks,
            'user' => $user,
            'projects' => Project::visibleTo($user)
                ->when($this->client, fn ($q) => \App\Livewire\Projects\Index::applyClientFilter($q, $this->client))
                ->orderBy('name')->get(['id', 'name']),
            'people' => $user->isManager() ? User::active()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
