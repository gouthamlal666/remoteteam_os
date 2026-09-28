{{-- Shared project form. Expects $form (ProjectForm), $departments, $people. --}}
<form wire:submit="saveProject" class="grid gap-4 sm:grid-cols-2">
    <x-field label="Client" for="p-client" error="form.client" class="sm:col-span-2">
        <input wire:model="form.client" id="p-client" list="p-client-options" class="input text-base font-medium" placeholder="e.g. ShopNest (leave empty for internal projects)" autocomplete="off">
        <datalist id="p-client-options">
            @foreach ($clients ?? [] as $c)
                @if ($c !== 'Internal') <option value="{{ $c }}"></option> @endif
            @endforeach
        </datalist>
        <p class="text-xs text-slate-400">Pick an existing client so their projects stay grouped together, or type a new one.</p>
    </x-field>
    <x-field label="Project name" for="p-name" error="form.name" class="sm:col-span-2">
        <input wire:model="form.name" id="p-name" class="input" placeholder="Client Website">
    </x-field>
    <x-field label="Department" for="p-dept" error="form.department_id">
        <select wire:model="form.department_id" id="p-dept" class="input">
            <option value="">— None —</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Status" for="p-status" error="form.status">
        <select wire:model="form.status" id="p-status" class="input">
            @foreach (\App\Enums\ProjectStatus::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Description" for="p-desc" error="form.description" class="sm:col-span-2">
        <textarea wire:model="form.description" id="p-desc" rows="3" class="input"></textarea>
    </x-field>
    <x-field label="Start date" for="p-start" error="form.start_date">
        <input wire:model="form.start_date" id="p-start" type="date" class="input">
    </x-field>
    <x-field label="Deadline" for="p-deadline" error="form.deadline">
        <input wire:model="form.deadline" id="p-deadline" type="date" class="input">
    </x-field>
    <x-field label="Colour" error="form.color" class="sm:col-span-2">
        <div class="flex gap-2 pt-1">
            @foreach (\App\Livewire\Forms\ProjectForm::COLORS as $c)
                <label class="cursor-pointer">
                    <input type="radio" wire:model="form.color" value="{{ $c }}" class="peer sr-only">
                    <span @class(['block size-7 rounded-full ring-offset-2 peer-checked:ring-2 peer-checked:ring-slate-900', \App\Support\Palette::dot($c)])></span>
                </label>
            @endforeach
        </div>
    </x-field>
    <x-field label="Team members" error="form.member_ids" class="sm:col-span-2">
        <div x-data="{ q: '' }" class="rounded-lg border border-slate-300">
            <input x-model="q" type="search" placeholder="Filter people…" class="w-full rounded-t-lg border-0 border-b border-slate-200 px-3 py-2 text-sm focus:ring-0 focus:outline-none">
            <div class="max-h-48 overflow-y-auto p-1">
                @foreach ($people as $person)
                    <label wire:key="pm-{{ $person->id }}" x-show="@js(strtolower($person->name.' '.$person->job_title)).includes(q.toLowerCase())" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50">
                        <input type="checkbox" wire:model="form.member_ids" value="{{ $person->id }}" class="rounded border-slate-300 text-indigo-600">
                        <span class="text-slate-800">{{ $person->name }}</span>
                        <span class="text-xs text-slate-400">{{ $person->job_title }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </x-field>
    <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
        <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
        <button type="submit" class="btn-primary">Save project</button>
    </div>
</form>
