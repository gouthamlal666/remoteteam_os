<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 flex-wrap gap-2">
            <div class="relative w-full sm:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search projects…" class="input pl-9">
            </div>
            <select wire:model.live="status" class="input w-auto">
                <option value="">All statuses</option>
                @foreach (\App\Enums\ProjectStatus::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="client" class="input w-auto max-w-56" aria-label="Filter by client">
                <option value="">All clients</option>
                @foreach ($clients as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
        @can('create', \App\Models\Project::class)
            <button wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> New project</button>
        @endcan
    </div>

    @if ($clientSummary)
        <section class="overflow-hidden rounded-xl border border-indigo-100 bg-white">
            <div class="flex flex-wrap items-center gap-4 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-white px-5 py-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">{{ mb_strtoupper(mb_substr($client, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium tracking-wide text-indigo-600 uppercase">Client</p>
                    <h2 class="truncate text-xl font-semibold text-slate-900">{{ $client }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('tasks.index', ['client' => $client, 'scope' => 'all']) }}" wire:navigate class="btn-secondary py-1.5">View all tasks</a>
                    <button wire:click="clearClient" class="btn-secondary py-1.5"><x-icon name="x" class="size-4" /> All clients</button>
                </div>
            </div>
            <div class="grid gap-6 p-5 lg:grid-cols-[18rem_1fr]">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Projects</p><p class="text-xl font-semibold">{{ $projects->count() }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Tasks</p><p class="text-xl font-semibold">{{ $clientSummary['total'] }}</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Completed</p><p class="text-xl font-semibold text-emerald-700">{{ $clientSummary['completed'] }}</p></div>
                        <div @class(['rounded-lg p-3', 'bg-red-50' => $clientSummary['overdue'], 'bg-slate-50' => ! $clientSummary['overdue']])><p @class(['text-xs', 'text-red-600' => $clientSummary['overdue'], 'text-slate-500' => ! $clientSummary['overdue']])>Overdue</p><p @class(['text-xl font-semibold', 'text-red-600' => $clientSummary['overdue']])>{{ $clientSummary['overdue'] }}</p></div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm"><span class="text-slate-500">Overall progress</span><span class="font-semibold">{{ $clientSummary['progress'] }}%</span></div>
                        <x-progress :value="$clientSummary['progress']" class="mt-2" />
                    </div>
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Open tasks <span class="font-normal text-slate-400">({{ $clientSummary['open'] }})</span></h3>
                    <ul class="divide-y divide-slate-100 rounded-lg border border-slate-100">
                        @forelse ($clientSummary['openTasks'] as $task)
                            <li wire:key="ct-{{ $task->id }}">
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50">
                                    <span class="size-2 shrink-0 rounded-full {{ \App\Support\Palette::dot($task->project->color) }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-slate-900">{{ $task->title }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ $task->project->name }} · {{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                                    </span>
                                    <x-task-status :task="$task" />
                                    <span @class(['hidden w-16 text-right text-xs tabular-nums sm:block', 'font-medium text-red-600' => $task->isOverdue(), 'text-slate-500' => ! $task->isOverdue()])>{{ $task->deadline?->format('d M') ?? '—' }}</span>
                                </a>
                            </li>
                        @empty
                            <li><x-empty class="py-6">No open tasks for {{ $client }} 🎉</x-empty></li>
                        @endforelse
                    </ul>
                    @if ($clientSummary['open'] > $clientSummary['openTasks']->count())
                        <a href="{{ route('tasks.index', ['client' => $client, 'scope' => 'all', 'status' => 'open']) }}" wire:navigate class="mt-2 inline-block text-sm text-indigo-600 hover:underline">See all {{ $clientSummary['open'] }} open tasks →</a>
                    @endif
                </div>
            </div>
        </section>
        <h3 class="pt-2 text-sm font-semibold text-slate-700">{{ $client }}'s projects</h3>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $project)
            <div wire:key="project-{{ $project->id }}" class="group/card relative">
            @can('delete', $project)
                <button wire:click="deleteProject({{ $project->id }})"
                        wire:confirm="Delete {{ $project->name }} and all {{ $project->tasks_count }} of its tasks, comments and files? This cannot be undone."
                        class="absolute right-3 bottom-3 z-10 hidden rounded-md bg-white p-1.5 text-slate-400 shadow-sm ring-1 ring-slate-200 group-hover/card:block hover:text-red-600 focus:block"
                        aria-label="Delete {{ $project->name }}"><x-icon name="trash" class="size-4" /></button>
            @endcan
            <div class="group relative flex h-full flex-col rounded-xl border border-t-4 border-slate-200 bg-white p-4 transition hover:shadow-md {{ \App\Support\Palette::bar($project->color) }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        @php $clientName = $project->client ?: 'Internal'; @endphp
                        <button type="button" wire:click="$set('client', @js($clientName))" title="Show only {{ $clientName }}'s projects and tasks"
                                class="relative z-10 block max-w-full truncate text-left text-lg leading-tight font-bold text-slate-900 decoration-indigo-300 decoration-2 underline-offset-4 hover:text-indigo-700 hover:underline">{{ $clientName }}</button>
                        <h3 class="mt-0.5 truncate text-sm font-medium text-slate-600 group-hover:text-indigo-700">
                            {{-- Stretched link: the whole card opens the project --}}
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="after:absolute after:inset-0 after:rounded-xl">{{ $project->name }}</a>
                        </h3>
                        @if ($project->department)
                            <p class="truncate text-xs text-slate-400">{{ $project->department->name }}</p>
                        @endif
                    </div>
                    <x-badge :color="$project->status->badge()">{{ $project->status->label() }}</x-badge>
                </div>

                <div class="mt-4">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ $project->completed_tasks_count }}/{{ $project->tasks_count }} tasks</span>
                        <span class="font-medium text-slate-700">{{ $project->progress() }}%</span>
                    </div>
                    <x-progress :value="$project->progress()" class="mt-1.5" />
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <div class="flex -space-x-2">
                        @foreach ($project->members->take(5) as $member)
                            <x-avatar :user="$member" size="size-7" />
                        @endforeach
                        @if ($project->members->count() > 5)
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-600 ring-2 ring-white">+{{ $project->members->count() - 5 }}</span>
                        @endif
                    </div>
                    <div class="text-right text-xs">
                        @if ($project->overdue_tasks_count)
                            <span class="font-medium text-red-600">{{ $project->overdue_tasks_count }} overdue</span> ·
                        @endif
                        <span class="text-slate-500">{{ $project->deadline ? 'Due '.$project->deadline->format('d M Y') : 'No deadline' }}</span>
                    </div>
                </div>
            </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white"><x-empty>{{ $client ? "No projects for {$client} match these filters." : 'No projects found.' }}</x-empty></div>
        @endforelse
    </div>

    @can('create', \App\Models\Project::class)
        <x-modal wire:model="showForm" title="New project">
            @include('livewire.projects._form')
        </x-modal>
    @endcan
</div>
