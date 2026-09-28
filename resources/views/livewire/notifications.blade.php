<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model.live="unreadOnly" type="checkbox" class="rounded border-slate-300 text-indigo-600"> Unread only
        </label>
        <div class="flex gap-2">
            <button wire:click="markAllRead" class="btn-secondary">Mark all as read</button>
            <button wire:click="clearRead" wire:confirm="Delete all read notifications?" class="btn-secondary text-red-600 hover:bg-red-50">Clear read</button>
        </div>
    </div>
    <x-card>
        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $n)
                <li wire:key="nn-{{ $n->id }}" class="group relative">
                    <button wire:click="delete('{{ $n->id }}')" class="absolute top-3 right-3 z-10 hidden rounded p-1 text-slate-400 group-hover:block hover:bg-red-50 hover:text-red-600" aria-label="Delete notification"><x-icon name="x" class="size-4" /></button>
                    <button wire:click="open('{{ $n->id }}')" @class(['flex w-full gap-3 px-4 py-3 text-left hover:bg-slate-50', 'bg-indigo-50/40' => ! $n->read_at])>
                        <span class="text-xl leading-none">{{ $n->data['icon'] ?? '🔔' }}</span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-sm', 'font-semibold text-slate-900' => ! $n->read_at, 'text-slate-700' => $n->read_at])>{{ $n->data['title'] ?? '' }}</span>
                            <span class="block text-sm text-slate-500">{{ $n->data['body'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 pr-6 text-xs text-slate-400" title="{{ $n->created_at->format('d M Y, h:i A') }}">{{ $n->created_at->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li><x-empty>No notifications.</x-empty></li>
            @endforelse
        </ul>
    </x-card>
    {{ $notifications->links() }}
</div>
