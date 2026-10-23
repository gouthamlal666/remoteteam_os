<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Component;

/** Ctrl/⌘ K global search across tasks, projects, people and meetings. */
class CommandPalette extends Component
{
    public string $query = '';

    public function results(): array
    {
        $user = auth()->user();
        $q = trim($this->query);

        $pages = collect([
            ['Dashboard', route('dashboard')], ['Projects', route('projects.index')], ['Tasks', route('tasks.index')],
            ['Chat', route('chat')], ['Meetings', route('meetings.index')], ['Attendance', route('attendance.index')], ['Leave', route('leave.index')], ['Holidays', route('attendance.index', ['tab' => 'holidays'])], ['Reports', route('reports.index')],
            ['Notifications', route('notifications')], ['Settings', route('settings')], ['Activity', route('activity.index')],
        ])->when($user->isManager(), fn ($c) => $c->push(['People', route('users.index')])->push(['Clients', route('clients.index')]))
            ->filter(fn ($p) => $q === '' || str_contains(strtolower($p[0]), strtolower($q)))
            ->map(fn ($p) => ['group' => 'Go to', 'title' => $p[0], 'subtitle' => null, 'url' => $p[1], 'icon' => '↗']);

        if (mb_strlen($q) < 2) {
            return $pages->values()->all();
        }

        $like = '%'.$q.'%';

        $tasks = Task::visibleTo($user)->with('project:id,name')->where('title', 'like', $like)
            ->orderByRaw("case status when 'completed' then 1 else 0 end")->limit(6)->get()
            ->map(fn (Task $t) => ['group' => 'Tasks', 'title' => $t->title, 'subtitle' => $t->project->name.' · '.$t->status->label(), 'url' => route('tasks.show', $t), 'icon' => '☑']);

        $projects = Project::visibleTo($user)->with('client:id,name')
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)))->limit(4)->get()
            ->map(fn (Project $p) => ['group' => 'Projects', 'title' => $p->name, 'subtitle' => $p->clientName(), 'url' => route('projects.show', $p), 'icon' => '📁']);

        $clients = $user->isManager()
            ? Client::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('industry', 'like', $like))->limit(4)->get()
                ->map(fn (Client $c) => ['group' => 'Clients', 'title' => $c->name, 'subtitle' => $c->industry, 'url' => route('clients.show', $c), 'icon' => '🏢'])
            : collect();

        $people = User::active()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('job_title', 'like', $like))->limit(5)->get()
            ->map(fn (User $u) => ['group' => 'People', 'title' => $u->name, 'subtitle' => $u->job_title, 'url' => route('users.show', $u), 'icon' => '👤']);

        $meetings = Meeting::visibleTo($user)->where('title', 'like', $like)->latest('starts_at')->limit(4)->get()
            ->map(fn (Meeting $m) => ['group' => 'Meetings', 'title' => $m->title, 'subtitle' => $m->starts_at->format('D d M, h:i A'), 'url' => route('meetings.show', $m), 'icon' => '📅']);

        return $pages->concat($tasks)->concat($projects)->concat($clients)->concat($people)->concat($meetings)->values()->all();
    }

    public function render()
    {
        return view('livewire.command-palette', ['results' => $this->results()]);
    }
}
