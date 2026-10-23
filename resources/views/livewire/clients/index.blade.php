<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 flex-wrap gap-2">
            <div class="relative w-full sm:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search clients…" class="input pl-9">
            </div>
            <select wire:model.live="status" class="input w-auto" aria-label="Filter by status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Client::STATUSES as $value => [$label]) <option value="{{ $value }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        @can('create', \App\Models\Client::class)
            <button wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> New client</button>
        @endcan
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($clients as $client)
            <a wire:key="client-{{ $client->id }}" href="{{ route('clients.show', $client) }}" wire:navigate
               class="group flex flex-col rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-300 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <x-client-logo :client="$client" size="size-12 text-base" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg leading-tight font-bold text-slate-900 group-hover:text-indigo-700">{{ $client->name }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $client->industry ?: 'No industry set' }}{{ $client->city ? ' · '.$client->city : '' }}</p>
                    </div>
                    <x-badge :color="$client->statusBadge()">{{ $client->statusLabel() }}</x-badge>
                </div>
                <div class="mt-4 space-y-1 text-sm text-slate-600">
                    @if ($client->website)
                        <p class="flex items-center gap-2 truncate"><span class="text-slate-400">🌐</span>{{ parse_url($client->website, PHP_URL_HOST) ?: $client->website }}</p>
                    @endif
                    @if ($client->primaryContact)
                        <p class="flex items-center gap-2 truncate"><x-icon name="user" class="size-4 shrink-0 text-slate-400" />{{ $client->primaryContact->name }}{{ $client->primaryContact->role ? ' · '.$client->primaryContact->role : '' }}</p>
                    @elseif ($client->email)
                        <p class="flex items-center gap-2 truncate"><x-icon name="mail" class="size-4 shrink-0 text-slate-400" />{{ $client->email }}</p>
                    @endif
                </div>
                <div class="mt-4 flex gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span><span class="font-semibold text-slate-900">{{ $client->projects_count }}</span> {{ str('project')->plural($client->projects_count) }}</span>
                    <span><span class="font-semibold text-slate-900">{{ $client->open_tasks_count }}</span> open {{ str('task')->plural($client->open_tasks_count) }}</span>
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white">
                <x-empty>
                    <p class="font-medium text-slate-700">{{ $search || $status ? 'No clients match these filters.' : 'No clients yet' }}</p>
                    @unless ($search || $status)
                        <p class="mt-1">Add your first client to keep their details, links, logins and files in one place.</p>
                    @endunless
                </x-empty>
            </div>
        @endforelse
    </div>

    @can('create', \App\Models\Client::class)
        <x-modal wire:model="showForm" title="New client" max-width="max-w-3xl">
            @include('livewire.clients._form')
        </x-modal>
    @endcan
</div>
