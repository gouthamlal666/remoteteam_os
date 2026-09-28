@php
    $user = auth()->user();
    $typeStyle = [
        'submission' => ['Submitted for review', 'border-amber-200 bg-amber-50'],
        'approval' => ['Approved', 'border-emerald-200 bg-emerald-50'],
        'revision' => ['Revision requested', 'border-orange-200 bg-orange-50'],
        'comment' => [null, 'border-slate-200 bg-white'],
    ];
@endphp
<div>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="text-slate-500 hover:text-slate-700">{{ $task->project->name }}</a>
                <span class="text-slate-300">/</span>
                <x-task-status :task="$task" />
                <x-badge :color="$task->priority->badge()">{{ $task->priority->label() }} priority</x-badge>
                @if ($task->revision_count)
                    <x-badge color="bg-orange-50 text-orange-700">{{ $task->revision_count }} {{ \Illuminate\Support\Str::plural('revision', $task->revision_count) }}</x-badge>
                @endif
            </div>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $task->title }}</h2>
            <div class="prose prose-sm mt-4 max-w-none text-sm whitespace-pre-line text-slate-700">{{ $task->description ?: 'No description.' }}</div>

            {{-- Workflow actions --}}
            <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                @if ($task->isAssignedTo($user) || $user->can('update', $task))
                    @if (in_array($task->status, [\App\Enums\TaskStatus::Todo, \App\Enums\TaskStatus::Revision]))
                        <button wire:click="start" class="btn-secondary">Start working</button>
                    @endif
                @endif
                @can('submit', $task)
                    <button wire:click="openDialog('submit')" class="btn-primary">Submit for review</button>
                @endcan
                @can('review', $task)
                    <button wire:click="openDialog('approve')" class="btn-success">Approve</button>
                    <button wire:click="openDialog('revision')" class="btn-secondary text-orange-700">Request revision</button>
                @endcan
                @can('update', $task)
                    <button x-on:click="$dispatch('open-task-editor', { taskId: {{ $task->id }} })" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</button>
                    <button wire:click="delete" wire:confirm="Delete this task, its comments and files?" class="btn-secondary ml-auto text-red-600 hover:bg-red-50"><x-icon name="trash" class="size-4" /></button>
                @endcan
            </div>

            @if ($dialog)
                @php
                    $dialogCopy = [
                        'submit' => ['Submit for review', 'What did you deliver? Links, notes for the reviewer…', 'btn-primary'],
                        'approve' => ['Approve task', 'Optional feedback', 'btn-success'],
                        'revision' => ['Request revision', 'What needs to change?', 'btn-primary'],
                    ][$dialog];
                @endphp
                <form wire:submit="confirmDialog" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-800">{{ $dialogCopy[0] }}</p>
                    <textarea wire:model="note" rows="3" class="input" placeholder="{{ $dialogCopy[1] }}" autofocus></textarea>
                    @error('note') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('dialog', null)" class="btn-secondary">Cancel</button>
                        <button type="submit" class="{{ $dialogCopy[2] }}">Confirm</button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Checklist --}}
        @php
            $items = $task->checklistItems;
            $doneCount = $items->where('is_done', true)->count();
            $canWork = $user->can('upload', $task);
        @endphp
        <x-card title="Checklist {{ $items->count() ? '('.$doneCount.'/'.$items->count().')' : '' }}">
            @if ($items->isNotEmpty())
                <div class="px-4 pt-3"><x-progress :value="$items->count() ? $doneCount / $items->count() * 100 : 0" color="bg-emerald-500" /></div>
            @endif
            <ul class="p-2">
                @forelse ($items as $item)
                    <li wire:key="ci-{{ $item->id }}" class="group flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                        <input type="checkbox" @checked($item->is_done) @disabled(! $canWork) wire:click="toggleItem({{ $item->id }})" class="rounded border-slate-300 text-emerald-600">
                        <span @class(['flex-1 text-sm', 'text-slate-400 line-through' => $item->is_done, 'text-slate-800' => ! $item->is_done])>{{ $item->title }}</span>
                        @if ($canWork)
                            <button wire:click="deleteItem({{ $item->id }})" class="hidden text-slate-400 group-hover:block hover:text-red-600" aria-label="Remove"><x-icon name="x" class="size-4" /></button>
                        @endif
                    </li>
                @empty
                    <li class="px-2 py-2 text-sm text-slate-500">Break this task into smaller steps.</li>
                @endforelse
            </ul>
            @if ($canWork)
                <form wire:submit="addItem" class="flex gap-2 border-t border-slate-100 p-3">
                    <input wire:model="newItem" class="input" placeholder="Add a checklist item…">
                    <button type="submit" class="btn-secondary">Add</button>
                </form>
                @error('newItem') <p class="px-3 pb-2 text-xs text-red-600">{{ $message }}</p> @enderror
            @endif
        </x-card>

        {{-- Discussion --}}
        <x-card title="Discussion ({{ $task->comments->count() }})">
            <ul class="space-y-3 p-4">
                @forelse ($task->comments->sortBy('created_at') as $c)
                    @php [$label, $style] = $typeStyle[$c->type] ?? $typeStyle['comment']; @endphp
                    <li wire:key="c-{{ $c->id }}" class="group flex gap-3">
                        <x-avatar :user="$c->user" />
                        <div class="min-w-0 flex-1 rounded-lg border px-3 py-2 {{ $style }}">
                            @if ($c->user_id === $user->id || $user->can('update', $task))
                                <button wire:click="deleteComment({{ $c->id }})" wire:confirm="Delete this comment?" class="float-right hidden text-slate-400 group-hover:block hover:text-red-600" aria-label="Delete comment"><x-icon name="trash" class="size-4" /></button>
                            @endif
                            <p class="text-xs text-slate-500">
                                <span class="font-medium text-slate-800">{{ $c->user->name }}</span>
                                @if ($label) · <span class="font-medium">{{ $label }}</span> @endif
                                · <time title="{{ $c->created_at->format('d M Y, h:i A') }}">{{ $c->created_at->diffForHumans() }}</time>
                            </p>
                            <p class="mt-1 text-sm whitespace-pre-line text-slate-700">{{ $c->body }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No comments yet.</li>
                @endforelse
            </ul>
            @can('comment', $task)
                <form wire:submit="addComment" class="flex items-start gap-3 border-t border-slate-100 p-4">
                    <x-avatar :user="$user" />
                    <div class="flex-1 space-y-2">
                        <textarea wire:model="comment" rows="2" class="input" placeholder="Write a comment…"></textarea>
                        @error('comment') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="flex justify-end"><button type="submit" class="btn-primary">Comment</button></div>
                    </div>
                </form>
            @endcan
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Details">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Deadline</dt><dd @class(['font-medium', 'text-red-600' => $task->isOverdue()])>{{ $task->deadline?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Start date</dt><dd>{{ $task->start_date?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Estimate</dt><dd>{{ $task->estimated_hours ? rtrim(rtrim($task->estimated_hours, '0'), '.').' h' : '—' }}</dd></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Created by</dt><dd>{{ $task->creator->name }}</dd></div>
                @if ($task->submitted_at)
                    <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Last submitted</dt><dd>{{ $task->submitted_at->format('d M Y') }}</dd></div>
                @endif
                @if ($task->completed_at)
                    <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-slate-500">Completed</dt><dd>{{ $task->completed_at->format('d M Y') }} @if (! $task->completedOnTime()) <span class="text-red-600">(late)</span> @endif</dd></div>
                @endif
            </dl>
            <div class="border-t border-slate-100 px-4 py-3">
                <p class="mb-2 text-xs font-medium text-slate-500 uppercase">Assignees</p>
                <ul class="space-y-2">
                    @forelse ($task->assignees as $a)
                        <li><a href="{{ route('users.show', $a) }}" wire:navigate class="flex items-center gap-2 text-sm hover:text-indigo-700"><x-avatar :user="$a" size="size-7" /> {{ $a->name }} <span class="text-xs text-slate-400">{{ $a->job_title }}</span></a></li>
                    @empty
                        <li class="text-sm text-slate-400">Unassigned</li>
                    @endforelse
                </ul>
            </div>
        </x-card>

        <x-card title="Time tracking">
            @php
                $estimate = $task->estimated_hours ? (float) $task->estimated_hours * 60 : null;
                $pct = $estimate ? min(100, $loggedMinutes / $estimate * 100) : 0;
            @endphp
            <div class="space-y-3 p-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ \App\Models\TimeEntry::format($loggedMinutes) }}</p>
                    <p class="text-xs text-slate-500">{{ $estimate ? 'of '.\App\Models\TimeEntry::format((int) $estimate).' estimated' : 'no estimate' }}</p>
                </div>
                @if ($estimate)
                    <x-progress :value="$pct" :color="$loggedMinutes > $estimate ? 'bg-red-500' : 'bg-indigo-500'" />
                @endif
                @can('upload', $task)
                    @if ($myTimer)
                        <button wire:click="stopTimer" class="btn-danger w-full">■ Stop timer (started {{ $myTimer->started_at->diffForHumans(null, true) }} ago)</button>
                    @else
                        <button wire:click="startTimer" class="btn-success w-full"><x-icon name="play" class="size-4" /> Start timer</button>
                    @endif
                    <details class="text-sm">
                        <summary class="cursor-pointer text-indigo-600">Log time manually</summary>
                        <form wire:submit="logTime" class="mt-2 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="logHours" type="number" step="0.25" min="0.1" max="24" class="input" placeholder="Hours" aria-label="Hours">
                                <input wire:model="logDate" type="date" max="{{ today()->toDateString() }}" class="input" aria-label="Date">
                            </div>
                            <input wire:model="logNote" class="input" placeholder="What did you work on? (optional)">
                            @error('logHours') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('logDate') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <button type="submit" class="btn-secondary w-full">Log time</button>
                        </form>
                    </details>
                @endcan
            </div>
            @if ($entries->isNotEmpty())
                <ul class="max-h-56 divide-y divide-slate-100 overflow-y-auto border-t border-slate-100">
                    @foreach ($entries as $e)
                        <li wire:key="te-{{ $e->id }}" class="group flex items-center gap-2 px-4 py-2 text-xs">
                            <span class="min-w-0 flex-1 truncate text-slate-600"><span class="font-medium text-slate-800">{{ $e->user->name }}</span> · {{ $e->started_at->format('d M') }}{{ $e->note ? ' · '.$e->note : '' }}</span>
                            <span @class(['tabular-nums', 'font-medium text-emerald-600' => $e->isRunning()])>{{ $e->isRunning() ? 'running' : \App\Models\TimeEntry::format($e->minutes) }}</span>
                            @if ($e->user_id === $user->id || $user->can('update', $task))
                                <button wire:click="deleteTimeEntry({{ $e->id }})" wire:confirm="Delete this time entry?" class="hidden text-slate-400 group-hover:block hover:text-red-600" aria-label="Delete"><x-icon name="x" class="size-3.5" /></button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Files ({{ $task->attachments->count() }})">
            <ul class="divide-y divide-slate-100">
                @forelse ($task->attachments as $file)
                    <li wire:key="f-{{ $file->id }}" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <x-icon name="paperclip" class="size-4 shrink-0 text-slate-400" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('attachments.download', $file) }}" class="block truncate font-medium text-slate-800 hover:text-indigo-700">{{ $file->original_name }}</a>
                            <p class="text-xs text-slate-400">{{ $file->humanSize() }} · {{ $file->user->name }} · {{ $file->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($user->can('update', $task) || $file->user_id === $user->id)
                            <button wire:click="deleteAttachment({{ $file->id }})" wire:confirm="Remove {{ $file->original_name }}?" class="text-slate-400 hover:text-red-600" aria-label="Remove file"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </li>
                @empty
                    <li><x-empty class="py-6">No files yet.</x-empty></li>
                @endforelse
            </ul>
            @can('upload', $task)
                <label class="flex cursor-pointer items-center justify-center gap-2 border-t border-slate-100 px-4 py-3 text-sm font-medium text-indigo-600 hover:bg-slate-50">
                    <input type="file" wire:model="upload" class="sr-only">
                    <x-icon name="plus" class="size-4" />
                    <span wire:loading.remove wire:target="upload">Upload file</span>
                    <span wire:loading wire:target="upload">Uploading…</span>
                </label>
                @error('upload') <p class="px-4 pb-3 text-xs text-red-600">{{ $message }}</p> @enderror
            @endcan
        </x-card>

        <x-card title="Timeline">
            <ol class="relative space-y-4 p-4 before:absolute before:top-5 before:bottom-5 before:left-[1.4rem] before:w-px before:bg-slate-200">
                @forelse ($activities as $activity)
                    <li class="relative flex gap-3 text-sm">
                        <span class="z-10 mt-1.5 size-2.5 shrink-0 rounded-full bg-indigo-400 ring-4 ring-white"></span>
                        <div>
                            <p class="text-slate-700"><span class="font-medium text-slate-900">{{ $activity->user?->name ?? 'System' }}</span> {{ $activity->description }}</p>
                            <p class="text-xs text-slate-400">{{ $activity->created_at->format('d M, h:i A') }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No activity yet.</li>
                @endforelse
            </ol>
        </x-card>
    </div>

</div>

    @can('update', $task)
        <livewire:tasks.task-editor />
    @endcan
</div>
