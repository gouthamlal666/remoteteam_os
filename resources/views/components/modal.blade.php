{{-- Controlled by a boolean Livewire property passed as wire:model, e.g. <x-modal wire:model="showForm"> --}}
@props(['title' => '', 'maxWidth' => 'max-w-2xl'])
<div x-data="{ open: @entangle($attributes->wire('model')).live }"
     x-show="open" x-cloak
     x-on:keydown.escape.window="open = false"
     class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/40" x-on:click="open = false"></div>
    <div class="relative flex min-h-full items-start justify-center p-4 sm:items-center">
        <div x-show="open" x-transition class="relative w-full {{ $maxWidth }} rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                <button type="button" x-on:click="open = false" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <x-icon name="x" />
                </button>
            </div>
            <div class="p-5">{{ $slot }}</div>
        </div>
    </div>
</div>
