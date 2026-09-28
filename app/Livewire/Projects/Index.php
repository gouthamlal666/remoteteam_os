<?php

namespace App\Livewire\Projects;

use App\Enums\TaskStatus;
use App\Livewire\Forms\ProjectForm;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Projects')]
class Index extends Component
{
    public ProjectForm $form;

    public bool $showForm = false;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** Client (business) name to focus on; "Internal" also matches projects with no client. */
    #[Url(except: '')]
    public string $client = '';

    /** Restricts a project query to the selected client. */
    public static function applyClientFilter(Builder $query, string $client, string $column = 'client'): void
    {
        $query->where(fn (Builder $q) => $client === 'Internal'
            ? $q->where($column, 'Internal')->orWhereNull($column)->orWhere($column, '')
            : $q->where($column, $client));
    }

    public function create(): void
    {
        $this->authorize('create', Project::class);
        $this->form->reset();
        $this->form->department_id = auth()->user()->department_id;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveProject(ActivityLogger $activity)
    {
        $this->authorize('create', Project::class);
        $project = $this->form->save(auth()->user(), $activity);

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function clearClient(): void
    {
        $this->client = '';
    }

    /** Everything about one client across the projects the viewer can see. */
    private function clientSummary(User $user): array
    {
        $tasks = Task::visibleTo($user)
            ->whereHas('project', fn (Builder $q) => self::applyClientFilter($q, $this->client))
            ->with(['project:id,name,color', 'assignees'])
            ->orderByRaw("case status when 'completed' then 1 else 0 end")
            ->orderByRaw('deadline is null, deadline')
            ->get();

        $completed = $tasks->where('status', TaskStatus::Completed)->count();

        return [
            'total' => $tasks->count(),
            'completed' => $completed,
            'open' => $tasks->count() - $completed,
            'overdue' => $tasks->filter->isOverdue()->count(),
            'progress' => $tasks->count() ? (int) round($completed / $tasks->count() * 100) : 0,
            'openTasks' => $tasks->where('status', '!=', TaskStatus::Completed)->take(8),
        ];
    }

    public function deleteProject(int $projectId, ActivityLogger $activity): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('delete', $project);

        $activity->log(auth()->user(), 'project.deleted', "deleted project {$project->name}");
        $project->delete();
        $this->dispatch('notify', message: "Deleted {$project->name}.");
    }

    public function render()
    {
        $user = auth()->user();

        $projects = Project::visibleTo($user)
            ->with(['members', 'department'])
            ->withProgressCounts()
            ->withCount(['tasks as overdue_tasks_count' => fn ($q) => $q->overdue()])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('client', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->client, fn ($q) => self::applyClientFilter($q, $this->client))
            ->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 else 3 end")
            ->orderByRaw('deadline is null, deadline')
            ->get();

        $clients = Project::visibleTo($user)->pluck('client')
            ->map(fn ($c) => filled($c) ? $c : 'Internal')->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('livewire.projects.index', [
            'projects' => $projects,
            'clients' => $clients,
            'clientSummary' => $this->client ? $this->clientSummary($user) : null,
            'departments' => $user->isManager() ? Department::orderBy('name')->get(['id', 'name']) : collect(),
            'people' => $user->isManager() ? User::active()->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
        ]);
    }
}
