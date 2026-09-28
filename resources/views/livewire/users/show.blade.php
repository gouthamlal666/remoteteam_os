<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center">
        <x-avatar :user="$user" size="size-16 text-lg" presence />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-semibold text-slate-900">{{ $user->name }}</h2>
                <x-badge :color="$user->role->badge()">{{ $user->role->label() }}</x-badge>
                @unless ($user->is_active) <x-badge>Inactive</x-badge> @endunless
            </div>
            <p class="text-sm text-slate-500">{{ $user->job_title ?: 'No title' }}{{ $user->department ? ' · '.$user->department->name : '' }} · {{ $user->email }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                @if ($user->activeStatus()) <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $user->activeStatus() }}</span> @endif
                <x-local-time :user="$user" />
                @if ($user->work_start && $user->work_end)
                    <span class="text-slate-400">Hours {{ substr($user->work_start, 0, 5) }}–{{ substr($user->work_end, 0, 5) }} ({{ $user->timezone }})</span>
                @endif
                <span class="text-slate-400">{{ $user->isOnline() ? 'Online now' : ($user->last_seen_at ? 'Last seen '.$user->last_seen_at->diffForHumans() : 'Not seen yet') }}</span>
            </p>
            @if ($user->bio) <p class="mt-2 text-sm text-slate-600">{{ $user->bio }}</p> @endif
            <div class="mt-3 flex gap-2">
                @can('messageUser', [\App\Models\Conversation::class, $user])
                    <a href="{{ route('chat.with', $user) }}" class="btn-primary py-1.5"><x-icon name="chat" class="size-4" /> Message</a>
                @endcan
                @if ($user->is(auth()->user()))
                    <a href="{{ route('settings') }}" wire:navigate class="btn-secondary py-1.5">Edit profile & status</a>
                @endif
                @can('manageEmployeeRecord', $user)
                    <a href="#employee" class="btn-secondary py-1.5"><x-icon name="id-card" class="size-4 text-indigo-500" /> Employee record</a>
                @endcan
                @can('delete', $user)
                    <button wire:click="delete"
                            wire:confirm="Permanently delete {{ $user->name }}?\n\nProjects, tasks and meetings they own will be transferred to you. Their comments, messages and files stay as &quot;Deleted user&quot;. Their employee record, documents, daily reports and direct messages are removed.\n\nTo keep everything, deactivate them instead."
                            class="btn-secondary py-1.5 text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /> Delete person</button>
                @endcan
            </div>
            @if ($user->skills)
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($user->skills as $skill)
                        <x-badge color="bg-indigo-50 text-indigo-700">{{ $skill }}</x-badge>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @can('viewEmployeeRecord', $user)
        <livewire:users.employee-record :user="$user" :key="'employee-'.$user->id" />
    @endcan

    @if ($stats)
        <div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
                <x-stat label="Assigned" :value="$stats['assigned']" />
                <x-stat label="Completed" :value="$stats['completed']" tone="text-emerald-600" />
                <x-stat label="Pending" :value="$stats['pending']" tone="text-amber-600" />
                <x-stat label="Overdue" :value="$stats['overdue']" tone="text-red-600" />
                <x-stat label="Completion" :value="$stats['completion_rate'].'%'" />
                <x-stat label="On time" :value="$stats['on_time_rate'].'%'" />
                <x-stat label="Revisions" :value="$stats['revisions']" />
                <x-stat label="Meetings" :value="$stats['meeting_attendance'] !== null ? $stats['meeting_attendance'].'%' : '—'" hint="attended" />
            </div>
            <p class="mt-2 text-xs text-slate-400">Calculated from assigned tasks and meeting attendance only. On time = completed on or before the deadline.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Current tasks">
            <ul class="divide-y divide-slate-100">
                @forelse ($currentTasks as $task)
                    <li>
                        <a href="{{ route('tasks.show', $task) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $task->title }}</p>
                                <p class="text-xs text-slate-500">{{ $task->project->name }}</p>
                            </div>
                            <x-task-status :task="$task" />
                        </a>
                    </li>
                @empty
                    <li><x-empty>No open tasks.</x-empty></li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Projects">
            <ul class="divide-y divide-slate-100">
                @forelse ($projects as $project)
                    <li>
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="block px-4 py-3 hover:bg-slate-50">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-slate-900">{{ $project->name }}</span>
                                <span class="text-xs text-slate-500">{{ $project->progress() }}%</span>
                            </div>
                            <x-progress :value="$project->progress()" class="mt-2" />
                        </a>
                    </li>
                @empty
                    <li><x-empty>Not on any projects.</x-empty></li>
                @endforelse
            </ul>
        </x-card>
    </div>

    @if ($stats && $contribution->isNotEmpty())
        <x-card title="Project contribution (completed tasks)">
            <ul class="space-y-3 p-4">
                @foreach ($contribution as $row)
                    <li>
                        <div class="flex justify-between text-sm"><span class="text-slate-700">{{ $row['project'] }}</span><span class="tabular-nums text-slate-900">{{ $row['share'] }}% <span class="text-xs text-slate-400">({{ $row['count'] }})</span></span></div>
                        <x-progress :value="$row['share']" class="mt-1.5" />
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($stats)
        <x-card title="Recent activity">
            <x-activity-feed :activities="$activities" />
        </x-card>
    @endif
</div>
