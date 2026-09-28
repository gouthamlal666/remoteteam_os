<?php

namespace App\Livewire\Users;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceService;
use App\Services\UserService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);
    }

    public function delete(UserService $users)
    {
        $this->authorize('delete', $this->user);
        $name = $this->user->name;
        $users->delete(auth()->user(), $this->user);
        session()->flash('status', "Deleted {$name}. Their projects, tasks and meetings are now yours.");

        return $this->redirectRoute('users.index', navigate: true);
    }

    public function render(PerformanceService $performance)
    {
        $viewer = auth()->user();
        $canSeePerformance = $viewer->can('viewPerformance', $this->user);

        // Only show work the viewer is allowed to see.
        $currentTasks = Task::assignedTo($this->user)->visibleTo($viewer)->pending()
            ->with('project')->orderByRaw('deadline is null, deadline')->limit(10)->get();

        $projects = Project::visibleTo($viewer)
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->whereKey($this->user->id))->orWhere('owner_id', $this->user->id))
            ->withProgressCounts()->orderBy('name')->get();

        return view('livewire.users.show', [
            'stats' => $canSeePerformance ? $this->user->taskStats() : null,
            'contribution' => $canSeePerformance ? $performance->projectContribution($this->user) : collect(),
            'currentTasks' => $currentTasks,
            'projects' => $projects,
            'activities' => $canSeePerformance
                ? $this->user->activities()->with(['user', 'project', 'subject'])->latest('created_at')->limit(10)->get()
                : collect(),
        ])->title($this->user->name);
    }
}
