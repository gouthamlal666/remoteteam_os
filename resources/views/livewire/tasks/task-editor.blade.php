<div>
    <x-modal wire:model="open" :title="$taskId ? 'Edit task' : 'New task'" max-width="max-w-3xl">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-field label="Task title" for="t-title" error="title" class="sm:col-span-2">
                <input wire:model="title" id="t-title" class="input" placeholder="Build Laravel API authentication">
            </x-field>
            <x-field label="Project" for="t-project" error="project_id">
                <select wire:model.live="project_id" id="t-project" class="input">
                    <option value="">Select project…</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Priority" for="t-priority" error="priority">
                <select wire:model="priority" id="t-priority" class="input">
                    @foreach (\App\Enums\TaskPriority::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Description" for="t-desc" error="description" class="sm:col-span-2">
                <textarea wire:model="description" id="t-desc" rows="4" class="input" placeholder="Requirements, acceptance criteria, links…"></textarea>
            </x-field>
            <x-field label="Start date" for="t-start" error="start_date">
                <input wire:model="start_date" id="t-start" type="date" class="input">
            </x-field>
            <x-field label="Deadline" for="t-deadline" error="deadline">
                <input wire:model="deadline" id="t-deadline" type="date" class="input">
            </x-field>
            <x-field label="Estimated hours" for="t-hours" error="estimated_hours">
                <input wire:model="estimated_hours" id="t-hours" type="number" step="0.5" min="0" class="input">
            </x-field>
            @if ($taskId)
                <x-field label="Status" for="t-status" error="status">
                    <select wire:model="status" id="t-status" class="input">
                        @foreach (\App\Enums\TaskStatus::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endif
            <x-field label="Assign to" error="assignee_ids" class="sm:col-span-2">
                <div x-data="{ q: '' }" class="rounded-lg border border-slate-300">
                    <input x-model="q" type="search" placeholder="Filter people…" class="w-full rounded-t-lg border-0 border-b border-slate-200 px-3 py-2 text-sm focus:ring-0 focus:outline-none">
                    <div class="max-h-44 overflow-y-auto p-1">
                        @foreach ($people as $person)
                            <label wire:key="ta-{{ $person->id }}" x-show="@js(strtolower($person->name.' '.$person->job_title)).includes(q.toLowerCase())" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50">
                                <input type="checkbox" wire:model="assignee_ids" value="{{ $person->id }}" class="rounded border-slate-300 text-indigo-600">
                                <span class="text-slate-800">{{ $person->name }}</span>
                                <span class="text-xs text-slate-400">{{ $person->job_title }}</span>
                                @if (in_array($person->id, $memberIds))
                                    <x-badge color="ml-auto bg-indigo-50 text-indigo-600">on project</x-badge>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
                <p class="text-xs text-slate-400">Assignees who aren't on the project are added to it automatically.</p>
            </x-field>
            <x-field label="Attachments" error="files.*" class="sm:col-span-2">
                <input wire:model="files" type="file" multiple class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                <div wire:loading wire:target="files" class="text-xs text-slate-500">Uploading…</div>
                @if ($files)
                    <ul class="mt-2 space-y-1">
                        @foreach ($files as $i => $file)
                            <li class="flex items-center justify-between rounded-md bg-slate-50 px-2 py-1 text-xs">
                                <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeFile({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="x" class="size-4" /></button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-field>
            <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
                <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,files">{{ $taskId ? 'Save changes' : 'Create task' }}</button>
            </div>
        </form>
    </x-modal>
</div>
