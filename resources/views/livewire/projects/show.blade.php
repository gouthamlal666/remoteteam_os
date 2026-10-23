@php $progress = $total ? (int) round($completed / $total * 100) : 0; @endphp
<div class="space-y-6">
    <div class="rounded-xl border border-t-4 border-slate-200 bg-white p-5 {{ \App\Support\Palette::bar($project->color) }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">Projects</a>
                    <span class="text-slate-300">/</span>
                    <x-badge :color="$project->status->badge()">{{ $project->status->label() }}</x-badge>
                </div>
<div class="mt-1 flex items-center gap-2">
                    @if ($project->client) <x-client-logo :client="$project->client" size="size-8 text-xs" /> @endif
                    <a href="{{ $project->client && auth()->user()->can('view', $project->client) ? route('clients.show', $project->client) : route('projects.index', ['client' => $project->client_id ?? 'internal']) }}" wire:navigate class="block text-2xl font-bold text-slate-900 hover:text-indigo-700" title="{{ $project->client ? 'Open client' : 'All internal projects' }}">{{ $project->clientName() }}</a>
                </div>
                <h2 class="text-lg font-semibold text-slate-600">{{ $project->name }}</h2>
                <p class="text-sm text-slate-500">
                    @if ($project->department) {{ $project->department->name }} · @endif
                    Owner {{ $project->owner->name }}
                </p>
                @if ($project->description)
                    <p class="mt-3 max-w-3xl text-sm whitespace-pre-line text-slate-600">{{ $project->description }}</p>
                @endif
            </div>
            <div class="flex shrink-0 gap-2">
                @if ($canManage)
                    <button x-on:click="$dispatch('open-task-editor', { projectId: {{ $project->id }} })" class="btn-primary"><x-icon name="plus" class="size-4" /> New task</button>
                    <button wire:click="edit" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</button>
                @endif
                @can('delete', $project)
                    <button wire:click="delete" wire:confirm="Delete {{ $project->name }} and all of its tasks? This cannot be undone." class="btn-secondary text-red-600 hover:bg-red-50" aria-label="Delete project"><x-icon name="trash" class="size-4" /></button>
                @endcan
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="col-span-2">
                <div class="flex justify-between text-sm"><span class="text-slate-500">Progress</span><span class="font-semibold">{{ $progress }}%</span></div>
                <x-progress :value="$progress" class="mt-2" />
            </div>
            <div><p class="text-xs text-slate-400">Tasks</p><p class="font-semibold">{{ $completed }}/{{ $total }}</p></div>
            <div><p class="text-xs text-slate-400">Overdue</p><p @class(['font-semibold', 'text-red-600' => $overdue])>{{ $overdue }}</p></div>
            <div><p class="text-xs text-slate-400">Deadline</p><p class="font-semibold">{{ $project->deadline?->format('d M Y') ?? '—' }}</p></div>
        </div>
    </div>

    {{-- Board --}}
    <div class="-mx-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6">
        <div class="grid min-w-[1000px] grid-cols-5 gap-3">
            @foreach ($columns as $key => $column)
                <div wire:key="col-{{ $key }}" class="rounded-xl bg-slate-100/70 p-2">
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <h3 class="text-xs font-semibold tracking-wide text-slate-600 uppercase">{{ $column['status']->label() }}</h3>
                        <span class="text-xs text-slate-400">{{ $column['tasks']->count() }}</span>
                    </div>
                    <div class="min-h-24 space-y-2" wire:sort="moveTask" wire:sort:group="board" wire:sort:group-id="{{ $key }}" wire:sort:config="{ forceFallback: true, fallbackTolerance: 4 }">
                        @foreach ($column['tasks'] as $task)
                            @php $checks = $task->checklist_items_count; @endphp
                            <div wire:key="task-{{ $task->id }}" wire:sort:item="{{ $task->id }}" class="group/task relative">
                            @if ($canManage)
                                <button wire:click="deleteTask({{ $task->id }})" wire:sort:ignore wire:confirm="Delete “{{ $task->title }}”, its comments and files?"
                                        class="absolute top-2 right-2 z-10 hidden rounded bg-white p-1 text-slate-400 ring-1 ring-slate-200 group-hover/task:block hover:text-red-600 [&_*]:pointer-events-none"
                                        aria-label="Delete {{ $task->title }}"><x-icon name="trash" class="size-3.5" /></button>
                            @endif
                            <a href="{{ route('tasks.show', $task) }}" wire:navigate
                               class="block cursor-grab rounded-lg border border-slate-200 bg-white p-3 pr-8 shadow-xs hover:border-indigo-300 active:cursor-grabbing">
                                <p class="text-sm font-medium text-slate-900">{{ $task->title }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <x-badge :color="$task->priority->badge()">{{ $task->priority->label() }}</x-badge>
                                    @if ($task->isOverdue())
                                        <x-badge color="bg-red-100 text-red-700">Overdue</x-badge>
                                    @endif
                                    @if ($task->revision_count)
                                        <x-badge color="bg-orange-50 text-orange-700">{{ $task->revision_count }} rev</x-badge>
                                    @endif
                                    @if ($checks)
                                        <x-badge color="bg-slate-50 text-slate-600">☑ {{ $task->checklist_done_count }}/{{ $checks }}</x-badge>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex -space-x-1.5">
                                        @foreach ($task->assignees as $assignee)
                                            <x-avatar :user="$assignee" size="size-6 text-[10px]" />
                                        @endforeach
                                    </div>
                                    <span @class(['text-xs', 'text-red-600' => $task->isOverdue(), 'text-slate-400' => ! $task->isOverdue()])>{{ $task->deadline?->format('d M') }}</span>
                                </div>
                            </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Team ({{ $project->members->count() }})">
            <ul class="divide-y divide-slate-100">
                @forelse ($project->members as $member)
                    <li wire:key="pm-{{ $member->id }}" class="group/member flex items-center hover:bg-slate-50">
                        <a href="{{ route('users.show', $member) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 px-4 py-2.5">
                            <x-avatar :user="$member" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $member->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $member->job_title ?: $member->role->label() }}</p>
                            </div>
                        </a>
                        @if ($canManage)
                            <button wire:click="removeMember({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from {{ $project->name }}? They will also be unassigned from its tasks."
                                    class="mr-3 hidden rounded p-1 text-slate-400 group-hover/member:block hover:bg-red-50 hover:text-red-600" aria-label="Remove {{ $member->name }}"><x-icon name="x" class="size-4" /></button>
                        @endif
                    </li>
                @empty
                    <li><x-empty>No members yet.</x-empty></li>
                @endforelse
            </ul>
        </x-card>
        <x-card title="Activity" class="lg:col-span-2">
            <x-activity-feed :activities="$activities" />
        </x-card>
    </div>

    @if ($canManage)
        <x-modal wire:model="showForm" title="Edit project">
            @include('livewire.projects._form')
        </x-modal>
        <livewire:tasks.task-editor />
    @endif
</div>
