<div x-data="{
        open: false,
        active: 0,
        show() { this.open = true; this.active = 0 },
        items() { return [...$el.querySelectorAll('[data-result]')] },
        move(d) { const n = this.items().length; if (n) { this.active = (this.active + d + n) % n; this.items()[this.active].scrollIntoView({ block: 'nearest' }) } },
        go() { const el = this.items()[this.active]; if (el) { this.open = false; Livewire.navigate(el.dataset.url) } },
     }"
     x-on:keydown.window.prevent.cmd.k="show()"
     x-on:keydown.window.prevent.ctrl.k="show()"
     x-on:open-palette.window="show()"
     x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 p-4 pt-[12vh]" x-on:click.self="open = false" x-transition.opacity>
        <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" x-trap.noscroll="open">
            <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                <x-icon name="search" class="size-5 text-slate-400" />
                <input x-ref="q" autofocus wire:model.live.debounce.200ms="query" type="text" placeholder="Search tasks, projects, people, meetings…"
                       x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="go()"
                       x-on:input="active = 0"
                       class="h-14 flex-1 border-0 bg-transparent text-sm focus:ring-0 focus:outline-none">
                <kbd class="rounded border border-slate-200 px-1.5 text-[10px] text-slate-400">ESC</kbd>
            </div>
            <div class="max-h-[60vh] overflow-y-auto p-2">
                @php $i = 0; @endphp
                @forelse (collect($results)->groupBy('group') as $group => $items)
                    <p class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $group }}</p>
                    @foreach ($items as $r)
                        <a href="{{ $r['url'] }}" wire:navigate data-result data-url="{{ $r['url'] }}" x-on:click="open = false"
                           x-on:mouseenter="active = {{ $i }}"
                           :class="active === {{ $i }} ? 'bg-indigo-50' : ''"
                           class="flex items-center gap-3 rounded-lg px-3 py-2">
                            <span class="w-5 text-center">{{ $r['icon'] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-slate-900">{{ $r['title'] }}</span>
                                @if ($r['subtitle']) <span class="block truncate text-xs text-slate-500">{{ $r['subtitle'] }}</span> @endif
                            </span>
                        </a>
                        @php $i++; @endphp
                    @endforeach
                @empty
                    <x-empty>No results for “{{ $query }}”.</x-empty>
                @endforelse
            </div>
        </div>
    </div>
</div>
