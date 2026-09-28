{{-- Shared meeting form. Expects $form, $people, $departments, $projects. --}}
<form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
    <x-field label="Title" for="m-title" error="form.title" class="sm:col-span-2">
        <input wire:model="form.title" id="m-title" class="input" placeholder="Weekly Development Meeting">
    </x-field>
    <x-field label="Date" for="m-date" error="form.date">
        <input wire:model="form.date" id="m-date" type="date" class="input">
    </x-field>
    <div class="grid grid-cols-2 gap-3">
        <x-field label="Time" for="m-time" error="form.time">
            <input wire:model="form.time" id="m-time" type="time" class="input">
        </x-field>
        <x-field label="Duration" for="m-dur" error="form.duration_minutes">
            <select wire:model="form.duration_minutes" id="m-dur" class="input">
                @foreach ([15, 30, 45, 60, 90, 120] as $d)
                    <option value="{{ $d }}">{{ $d }} min</option>
                @endforeach
            </select>
        </x-field>
    </div>
    <x-field label="Meeting link" for="m-link" error="form.meeting_link">
        <input wire:model="form.meeting_link" id="m-link" type="url" class="input" placeholder="https://meet.google.com/…">
    </x-field>
    <x-field label="Project (optional)" for="m-project" error="form.project_id">
        <select wire:model="form.project_id" id="m-project" class="input">
            <option value="">— None —</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Agenda" for="m-agenda" error="form.agenda" class="sm:col-span-2">
        <textarea wire:model="form.agenda" id="m-agenda" rows="3" class="input" placeholder="1. Sprint review&#10;2. Blockers&#10;3. Next week's plan"></textarea>
    </x-field>
    <x-field label="Participants" error="form.participant_ids" class="sm:col-span-2">
        <div class="mb-2 flex flex-wrap gap-1.5">
            @foreach ($departments as $d)
                <button type="button" wire:click="addDepartment({{ $d->id }})" class="rounded-full border border-slate-200 px-2.5 py-0.5 text-xs text-slate-600 hover:border-indigo-300 hover:text-indigo-700">+ {{ $d->name }}</button>
            @endforeach
        </div>
        <div x-data="{ q: '' }" class="rounded-lg border border-slate-300">
            <input x-model="q" type="search" placeholder="Filter people…" class="w-full rounded-t-lg border-0 border-b border-slate-200 px-3 py-2 text-sm focus:ring-0 focus:outline-none">
            <div class="max-h-44 overflow-y-auto p-1">
                @foreach ($people as $person)
                    <label wire:key="mp-{{ $person->id }}" x-show="@js(strtolower($person->name.' '.$person->job_title)).includes(q.toLowerCase())" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50">
                        <input type="checkbox" wire:model="form.participant_ids" value="{{ $person->id }}" class="rounded border-slate-300 text-indigo-600">
                        <span class="text-slate-800">{{ $person->name }}</span>
                        <span class="text-xs text-slate-400">{{ $person->job_title }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </x-field>
    <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
        <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
        <button type="submit" class="btn-primary">{{ $form->meeting ? 'Save changes' : 'Schedule meeting' }}</button>
    </div>
</form>
