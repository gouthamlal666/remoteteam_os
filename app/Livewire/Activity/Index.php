<?php

namespace App\Livewire\Activity;

use App\Models\Activity;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Activity')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $project = '';

    public function updatingProject(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $activities = Activity::visibleTo($user)
            ->with(['user', 'project', 'subject'])
            ->when($this->project, fn ($q) => $q->where('project_id', $this->project))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30);

        return view('livewire.activity.index', [
            'groups' => $activities->getCollection()->groupBy(fn (Activity $a) => $a->created_at->toDateString()),
            'activities' => $activities,
            'projects' => Project::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
