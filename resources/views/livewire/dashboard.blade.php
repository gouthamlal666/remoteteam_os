<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold text-slate-900">{{ $greeting }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h2>
        <p class="text-sm text-slate-500">{{ now()->format('l, d F Y') }} · {{ $user->role->label() }}{{ $user->department ? ' · '.$user->department->name : '' }}</p>
    </div>

    @if ($user->isMember())
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-stat label="Assigned" :value="$stats['assigned']" />
            <x-stat label="Completed" :value="$stats['completed']" tone="text-emerald-600" />
            <x-stat label="Pending" :value="$stats['pending']" tone="text-amber-600" />
            <x-stat label="Overdue" :value="$stats['overdue']" tone="text-red-600" />
            <x-stat label="On-time rate" :value="$stats['on_time_rate'].'%'" hint="of completed tasks" />
            <x-stat label="Projects" :value="$stats['projects']" />
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @if ($user->isSuperAdmin())
                <x-stat label="Admins" :value="$stats['admins']" />
                <x-stat label="Departments" :value="$stats['departments']" />
            @endif
            <x-stat label="Members" :value="$stats['members']" />
            <x-stat label="Projects" :value="$stats['projects']" />
            <x-stat label="Tasks" :value="$stats['tasks']" />
            <x-stat label="Completed" :value="$stats['completed']" tone="text-emerald-600" />
            <x-stat label="Pending" :value="$stats['pending']" tone="text-amber-600" />
            <x-stat label="Overdue" :value="$stats['overdue']" tone="text-red-600" />
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="$user->isMember() ? 'Needs your attention' : 'Due soon & awaiting review'">
                <ul class="divide-y divide-slate-100">
                    @forelse ($focusTasks as $task)
                        <li>
                            <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $task->title }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $task->project->name }} · {{ $task->assignees->pluck('name')->join(', ') ?: 'Unassigned' }}</p>
                                </div>
                                <x-task-status :task="$task" />
                                <span @class(['hidden w-20 shrink-0 text-right text-xs tabular-nums sm:block', 'text-red-600 font-medium' => $task->isOverdue(), 'text-slate-500' => ! $task->isOverdue()])>
                                    {{ $task->deadline?->format('d M') ?? '—' }}
                                </span>
                            </a>
                        </li>
                    @empty
                        <li><x-empty>Nothing urgent. Nice work.</x-empty></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Active projects">
                <x-slot:actions>
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">View all</a>
                </x-slot:actions>
                <ul class="divide-y divide-slate-100">
                    @forelse ($projects as $project)
                        <li>
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="block px-4 py-3 hover:bg-slate-50">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-slate-900">{{ $project->name }}</span>
                                    <span class="shrink-0 text-xs text-slate-500">{{ $project->completed_tasks_count }}/{{ $project->tasks_count }} tasks · {{ $project->progress() }}%</span>
                                </div>
                                <x-progress :value="$project->progress()" class="mt-2" />
                            </a>
                        </li>
                    @empty
                        <li><x-empty>No active projects.</x-empty></li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            @unless ($reportedToday)
                <a href="{{ route('reports.index') }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4 hover:bg-indigo-100">
                    <x-icon name="chart" class="size-6 text-indigo-600" />
                    <span class="text-sm"><span class="block font-semibold text-indigo-900">Submit today's report</span><span class="text-indigo-700">What you did, what's next, any blockers.</span></span>
                </a>
            @endunless

            <x-card title="Upcoming meetings">
                <x-slot:actions>
                    <a href="{{ route('meetings.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">View all</a>
                </x-slot:actions>
                <div class="divide-y divide-slate-100">
                    @forelse ($meetings as $meeting)
                        <x-meeting-row :meeting="$meeting" :user="$user" />
                    @empty
                        <x-empty class="py-6">No meetings scheduled.</x-empty>
                    @endforelse
                </div>
            </x-card>

            @if ($user->isManager())
                <x-card title="Department performance">
                    <ul class="space-y-4 p-4">
                        @forelse ($departments as $row)
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-700">{{ $row['department']->name }}</span>
                                    <span class="tabular-nums text-slate-900">{{ $row['completion_rate'] }}%</span>
                                </div>
                                <x-progress :value="$row['completion_rate']" class="mt-1.5" />
                                <p class="mt-1 text-xs text-slate-400">{{ $row['completed'] }}/{{ $row['total'] }} done · {{ $row['on_time_rate'] }}% on time · {{ $row['overdue'] }} overdue</p>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">No departments yet.</li>
                        @endforelse
                    </ul>
                    <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">Completion rate = completed ÷ all tasks in the department's projects.</p>
                </x-card>
            @endif

        </div>
    </div>

    <livewire:dashboard-calendar />
</div>
