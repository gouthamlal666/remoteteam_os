<div class="flex flex-1 items-center gap-2" wire:poll.20s.visible="check">
    <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900">{{ $title }}</h1>

    {{-- Search / command palette --}}
    <button type="button" x-on:click="$dispatch('open-palette')" class="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-500 hover:bg-slate-50">
        <x-icon name="search" class="size-4" />
        <span class="hidden md:inline">Search</span>
        <kbd class="hidden rounded border border-slate-200 px-1 text-[10px] md:inline" x-text="navigator.platform.includes('Mac') ? '⌘K' : 'Ctrl K'"></kbd>
    </button>

    {{-- Running timer --}}
    @if ($timer)
        <div x-data="{ start: {{ $timer->started_at->timestamp }}, now: Math.floor(Date.now() / 1000), get label() { let s = Math.max(0, this.now - this.start); return [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':') } }"
             x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)"
             class="flex items-center gap-2 rounded-lg bg-emerald-50 py-1 pr-1 pl-2.5 text-sm text-emerald-800">
            <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span></span>
            <a href="{{ route('tasks.show', $timer->task_id) }}" wire:navigate class="hidden max-w-40 truncate hover:underline sm:inline">{{ $timer->task->title }}</a>
            <span class="font-mono tabular-nums" x-text="label"></span>
            <button wire:click="stopTimer" class="rounded-md bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-emerald-500">Stop</button>
        </div>
    @endif

    {{-- Notification bell --}}
    <div x-data="{
            open: false,
            permission: 'Notification' in window ? Notification.permission : 'unsupported',
            ask() { Notification.requestPermission().then(p => this.permission = p) },
         }"
         x-on:click.outside="open = false"
         x-on:browser-notify.window="if (permission === 'granted' && document.visibilityState !== 'visible') { const n = new Notification($event.detail.title, { body: $event.detail.body, tag: $event.detail.url }); n.onclick = () => { window.focus(); window.location = $event.detail.url } } else { $dispatch('notify', { message: $event.detail.title + ' — ' + $event.detail.body }) }"
         class="relative">
        <button type="button" x-on:click="open = !open" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
            @if ($unread)
                <span class="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
            @endif
        </button>

        <div x-show="open" x-cloak x-transition
             class="absolute right-0 z-40 mt-2 w-[calc(100vw-2rem)] max-w-sm overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                @if ($unread)
                    <button wire:click="markAllRead" class="text-xs font-medium text-indigo-600 hover:underline">Mark all read</button>
                @endif
            </div>
            <template x-if="permission === 'default'">
                <button x-on:click="ask()" class="w-full border-b border-slate-100 bg-indigo-50 px-4 py-2 text-left text-xs text-indigo-700 hover:bg-indigo-100">🔔 Enable desktop notifications</button>
            </template>
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($notifications as $n)
                    <li wire:key="n-{{ $n->id }}">
                        <button wire:click="open('{{ $n->id }}')" @class(['flex w-full gap-3 px-4 py-3 text-left hover:bg-slate-50', 'bg-indigo-50/40' => ! $n->read_at])>
                            <span class="text-lg leading-none">{{ $n->data['icon'] ?? '🔔' }}</span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block text-sm', 'font-semibold text-slate-900' => ! $n->read_at, 'text-slate-700' => $n->read_at])>{{ $n->data['title'] ?? '' }}</span>
                                <span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 110) }}</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
                            </span>
                            @unless ($n->read_at) <span class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-500"></span> @endunless
                        </button>
                    </li>
                @empty
                    <li><x-empty class="py-8">You're all caught up 🎉</x-empty></li>
                @endforelse
            </ul>
            <a href="{{ route('notifications') }}" wire:navigate class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-indigo-600 hover:bg-slate-50">View all</a>
        </div>
    </div>
</div>
