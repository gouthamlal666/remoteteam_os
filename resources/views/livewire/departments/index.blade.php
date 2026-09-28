<div class="space-y-4">
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $departments->count() }} departments</p>
        @if ($canManage)
            <button wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> New department</button>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($departments as $department)
            <div wire:key="dept-{{ $department->id }}" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="size-3 rounded-full {{ \App\Support\Palette::dot($department->color) }}"></span>
                        <h3 class="font-semibold text-slate-900">{{ $department->name }}</h3>
                    </div>
                    @if ($canManage)
                        <div class="flex gap-1">
                            <button wire:click="edit({{ $department->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit"><x-icon name="pencil" class="size-4" /></button>
                            <button wire:click="delete({{ $department->id }})" wire:confirm="Delete {{ $department->name }}? Members and projects stay but lose their department." class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    @endif
                </div>
                <p class="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{{ $department->description ?: 'No description.' }}</p>
                <dl class="mt-4 flex gap-6 text-sm">
                    <div><dt class="text-xs text-slate-400">People</dt><dd class="font-semibold">{{ $department->users_count }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Projects</dt><dd class="font-semibold">{{ $department->projects_count }}</dd></div>
                    <div class="min-w-0"><dt class="text-xs text-slate-400">Manager</dt><dd class="truncate font-semibold">{{ $department->manager?->name ?? '—' }}</dd></div>
                </dl>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white"><x-empty>No departments yet.</x-empty></div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit department' : 'New department'" max-width="max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Name" for="d-name" error="name">
                <input wire:model="name" id="d-name" class="input" placeholder="Development">
            </x-field>
            <x-field label="Description" for="d-desc" error="description">
                <textarea wire:model="description" id="d-desc" rows="3" class="input"></textarea>
            </x-field>
            <x-field label="Manager (admin)" for="d-manager" error="manager_id">
                <select wire:model="manager_id" id="d-manager" class="input">
                    <option value="">— None —</option>
                    @foreach ($admins as $admin)
                        <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Colour" error="color">
                <div class="flex gap-2">
                    @foreach (\App\Livewire\Departments\Index::COLORS as $key)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="color" value="{{ $key }}" class="peer sr-only">
                            <span class="block size-7 rounded-full {{ \App\Support\Palette::dot($key) }} ring-offset-2 peer-checked:ring-2 peer-checked:ring-slate-900"></span>
                        </label>
                    @endforeach
                </div>
            </x-field>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</div>
