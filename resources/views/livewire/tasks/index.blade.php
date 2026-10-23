<div class="space-y-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach (['mine' => 'Assigned to me', 'all' => $user->isMember() ? 'My projects' : 'All tasks'] as $value => $label)
                <button wire:click="$set('scope', '{{ $value }}')" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-indigo-600 text-white' => $scope === $value, 'text-slate-600 hover:bg-slate-50' => $scope !== $value])>{{ $label }}</button>
            @endforeach
        </div>
        @can('create', \App\Models\Task::class)
            <button x-on:click="$dispatch('open-task-editor')" class="btn-primary"><x-icon name="plus" class="size-4" /> New task</button>
        @endcan
    </div>

    @if ($client)
        <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-500">Client:</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 py-1 pr-1 pl-3 font-medium text-indigo-700 ring-1 ring-indigo-200">
                <a href="{{ route('projects.index', ['client' => $client]) }}" wire:navigate class="hover:underline">{{ $clientLabel ?? 'Unknown client' }}</a>
                <button wire:click="$set('client', '')" class="rounded-full p-0.5 hover:bg-indigo-100" aria-label="Remove client filter"><x-icon name="x" class="size-3.5" /></button>
            </span>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <div class="relative w-full sm:w-60">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search tasks…" class="input pl-9">
        </div>
        <select wire:model.live="status" class="input w-auto">
            <option value="">Any status</option>
            <option value="open">Open (not completed)</option>
            <option value="overdue">Overdue</option>
            @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="priority" class="input w-auto">
            <option value="">Any priority</option>
            @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="project" class="input w-auto max-w-52">
            <option value="">All projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        @if ($people->isNotEmpty() && $scope === 'all')
            <select wire:model.live="assignee" class="input w-auto max-w-52">
                <option value="">Anyone</option>
                @foreach ($people as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        @endif
        @if ($search || $status || $priority || $project || $assignee || $client)
            <button wire:click="clearFilters" class="text-sm text-slate-500 hover:text-slate-800">Clear</button>
        @endif
    </div>

    @php
        $deletableIds = $user->isManager() ? $tasks->filter(fn ($t) => $user->can('delete', $t))->pluck('id')->map(fn ($id) => (string) $id)->values() : collect();
    @endphp
    @if ($selected)
        <div class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm">
            <span class="text-red-800">{{ count($selected) }} selected</span>
            <div class="flex gap-2">
                <button wire:click="$set('selected', [])" class="btn-secondary py-1">Clear</button>
                <button wire:click="deleteSelected" wire:confirm="Delete {{ count($selected) }} {{ str('task')->plural(count($selected)) }} with their comments and files? This cannot be undone." class="btn-danger py-1"><x-icon name="trash" class="size-4" /> Delete selected</button>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase">
                    <tr>
                        @if ($deletableIds->isNotEmpty())
                            <th class="w-10 py-3 pl-4">
                                <input type="checkbox" aria-label="Select all on this page" class="rounded border-slate-300 text-indigo-600"
                                       @checked($deletableIds->diff($selected)->isEmpty())
                                       x-on:change="$wire.set('selected', $event.target.checked ? @js($deletableIds) : [])">
                            </th>
                        @endif
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Assignees</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Deadline</th>
                        @if ($deletableIds->isNotEmpty()) <th class="w-10"></th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tasks as $task)
                        <tr wire:key="task-{{ $task->id }}" class="group hover:bg-slate-50">
                            @if ($deletableIds->isNotEmpty())
                                <td class="py-3 pl-4">
                                    @if ($deletableIds->contains((string) $task->id))
                                        <input type="checkbox" wire:model.live="selected" value="{{ $task->id }}" aria-label="Select {{ $task->title }}" class="rounded border-slate-300 text-indigo-600">
                                    @endif
                                </td>
                            @endif
                            <td class="max-w-sm px-4 py-3">
                                <a href="{{ route('tasks.show', $task) }}" wire:navigate class="block truncate font-medium text-slate-900 hover:text-indigo-700">{{ $task->title }}</a>
                                <p class="truncate text-xs text-slate-500">{{ $task->project->name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex -space-x-1.5">
                                    @forelse ($task->assignees as $a)
                                        <x-avatar :user="$a" size="size-7" />
                                    @empty
                                        <span class="text-xs text-slate-400">Unassigned</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3"><x-badge :color="$task->priority->badge()">{{ $task->priority->label() }}</x-badge></td>
                            <td class="px-4 py-3"><x-task-status :task="$task" /></td>
                            <td @class(['px-4 py-3 text-right tabular-nums whitespace-nowrap', 'font-medium text-red-600' => $task->isOverdue(), 'text-slate-600' => ! $task->isOverdue()])>
                                {{ $task->deadline?->format('d M Y') ?? '—' }}
                            </td>
                            @if ($deletableIds->isNotEmpty())
                                <td class="pr-3">
                                    @if ($deletableIds->contains((string) $task->id))
                                        <button wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete “{{ $task->title }}”, its comments and files?" class="invisible rounded p-1 text-slate-400 group-hover:visible hover:bg-red-50 hover:text-red-600 focus:visible" aria-label="Delete {{ $task->title }}"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty>No tasks match these filters.</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $tasks->links() }}

    @can('create', \App\Models\Task::class)
        <livewire:tasks.task-editor />
    @endcan
</div>
