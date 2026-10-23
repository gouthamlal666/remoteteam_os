@php
    $tabs = [
        'overview' => ['Overview', null],
        'projects' => ['Projects', $summary['projects']],
        'links' => ['Links', $client->links->count()],
        'vault' => ['Passwords', $credentialCount],
        'files' => ['Files & content', $client->files->count()],
    ];
@endphp
<div class="space-y-6">
    {{-- ───────── Header ───────── --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-col gap-5 bg-gradient-to-r from-indigo-50/80 via-white to-white p-5 sm:flex-row sm:items-center">
            <div class="relative">
                <x-client-logo :client="$client" size="size-20 text-2xl" />
                @can('update', $client)
                    <label class="absolute -right-1 -bottom-1 flex size-7 cursor-pointer items-center justify-center rounded-full bg-white text-slate-500 shadow ring-1 ring-slate-200 hover:text-indigo-600" title="{{ $client->logo_path ? 'Change logo' : 'Upload logo' }}">
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only">
                        <x-icon name="pencil" class="size-3.5" />
                    </label>
                @endcan
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('clients.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">Clients</a>
                    <span class="text-slate-300">/</span>
                    <x-badge :color="$client->statusBadge()">{{ $client->statusLabel() }}</x-badge>
                    @if ($client->industry) <x-badge>{{ $client->industry }}</x-badge> @endif
                </div>
                <h2 class="mt-1 truncate text-2xl font-bold text-slate-900">{{ $client->name }}</h2>
                @if ($client->legal_name) <p class="text-sm text-slate-500">{{ $client->legal_name }}</p> @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($client->website)
                        <a href="{{ $client->website }}" target="_blank" rel="noopener noreferrer" class="btn-secondary py-1.5">🌐 {{ parse_url($client->website, PHP_URL_HOST) ?: 'Website' }}</a>
                    @endif
                    @if ($client->email)
                        <a href="mailto:{{ $client->email }}" class="btn-secondary py-1.5"><x-icon name="mail" class="size-4" /> Email</a>
                    @endif
                    @if ($client->phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" class="btn-secondary py-1.5"><x-icon name="phone" class="size-4" /> Call</a>
                    @endif
                    @if ($client->whatsappUrl())
                        <a href="{{ $client->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="btn-secondary py-1.5 text-emerald-700">💬 WhatsApp</a>
                    @endif
                </div>
                @error('logo') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex shrink-0 gap-2 sm:self-start">
                @can('update', $client)
                    <button wire:click="edit" class="btn-primary"><x-icon name="pencil" class="size-4" /> Edit details</button>
                @endcan
                @can('delete', $client)
                    <button wire:click="delete" wire:confirm="Delete {{ $client->name }}?\n\nTheir contacts, links, saved passwords and files are permanently removed. Their {{ $summary['projects'] }} {{ str('project')->plural($summary['projects']) }} are kept as internal projects."
                            class="btn-secondary text-red-600 hover:bg-red-50" aria-label="Delete client"><x-icon name="trash" class="size-4" /></button>
                @endcan
            </div>
        </div>
        <div class="grid grid-cols-2 border-t border-slate-100 sm:grid-cols-4">
            @foreach ([['Projects', $summary['projects'], 'text-slate-900'], ['Open tasks', $summary['open'], 'text-amber-600'], ['Overdue', $summary['overdue'], $summary['overdue'] ? 'text-red-600' : 'text-slate-900'], ['Progress', $summary['progress'].'%', 'text-indigo-600']] as [$label, $value, $tone])
                <div class="border-slate-100 px-5 py-3 [&:not(:last-child)]:border-r">
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                    <p class="text-xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────── Tabs ───────── --}}
    <nav class="flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Client sections">
        @foreach ($tabs as $key => [$label, $count])
            <button wire:click="$set('tab', '{{ $key }}')" @class([
                '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap',
                'border-indigo-600 text-indigo-700' => $tab === $key,
                'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key,
            ])>
                {{ $label }}
                @if ($count !== null) <span class="rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{{ $count }}</span> @endif
                @if ($key === 'vault') <x-icon name="shield" class="size-3.5 text-slate-400" /> @endif
            </button>
        @endforeach
    </nav>

    {{-- ───────── Overview ───────── --}}
    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Details">
                    @php
                        $rows = [
                            ['Website', $client->website, 'link'], ['Email', $client->email, 'mail'], ['Phone', $client->phone, 'tel'],
                            ['WhatsApp', $client->whatsapp, 'tel'], ['GST / tax no.', $client->tax_id, null], ['Client since', $client->client_since?->format('d M Y'), null],
                            ['Address', $client->fullAddress(), null], ['Added by', $client->creator?->name, null],
                        ];
                    @endphp
                    <dl class="grid gap-x-6 gap-y-3 p-5 text-sm sm:grid-cols-2">
                        @foreach ($rows as [$label, $value, $kind])
                            <div class="grid grid-cols-[7rem_1fr] gap-2">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="min-w-0 font-medium break-words text-slate-900">
                                    @if (! filled($value))
                                        <span class="font-normal text-slate-300">—</span>
                                    @elseif ($kind === 'link')
                                        <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-indigo-700 hover:underline">{{ $value }}</a>
                                    @elseif ($kind === 'mail')
                                        <a href="mailto:{{ $value }}" class="text-indigo-700 hover:underline">{{ $value }}</a>
                                    @elseif ($kind === 'tel')
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $value) }}" class="text-indigo-700 hover:underline">{{ $value }}</a>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($client->notes)
                        <div class="border-t border-slate-100 p-5">
                            <p class="mb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">Notes</p>
                            <p class="text-sm whitespace-pre-line text-slate-700">{{ $client->notes }}</p>
                        </div>
                    @endif
                </x-card>

                <x-card title="Active projects">
                    <x-slot:actions>
                        <a href="{{ route('projects.index', ['client' => $client->id, 'new' => 1]) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline">+ New project</a>
                    </x-slot:actions>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($projects->where('status', '!=', \App\Enums\ProjectStatus::Completed)->take(5) as $p)
                            <li>
                                <a href="{{ route('projects.show', $p) }}" wire:navigate class="block px-4 py-3 hover:bg-slate-50">
                                    <div class="flex justify-between text-sm"><span class="font-medium text-slate-900">{{ $p->name }}</span><span class="text-xs text-slate-500">{{ $p->progress() }}%</span></div>
                                    <x-progress :value="$p->progress()" class="mt-2" />
                                </a>
                            </li>
                        @empty
                            <li><x-empty class="py-6">No active projects for {{ $client->name }}.</x-empty></li>
                        @endforelse
                    </ul>
                </x-card>
            </div>

            <x-card title="Contacts">
                <x-slot:actions>
                    @can('update', $client)
                        <button wire:click="editContact" class="text-sm font-medium text-indigo-600 hover:underline">+ Add</button>
                    @endcan
                </x-slot:actions>
                <ul class="divide-y divide-slate-100">
                    @forelse ($client->contacts as $c)
                        <li wire:key="ct-{{ $c->id }}" class="group px-4 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $c->name }} @if ($c->is_primary) <x-badge color="ml-1 bg-indigo-50 text-indigo-700">Main contact</x-badge> @endif</p>
                                    @if ($c->role) <p class="text-xs text-slate-500">{{ $c->role }}</p> @endif
                                </div>
                                @can('update', $client)
                                    <div class="hidden shrink-0 gap-1 group-hover:flex">
                                        <button wire:click="editContact({{ $c->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit {{ $c->name }}"><x-icon name="pencil" class="size-3.5" /></button>
                                        <button wire:click="deleteContact({{ $c->id }})" wire:confirm="Remove {{ $c->name }}?" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove {{ $c->name }}"><x-icon name="trash" class="size-3.5" /></button>
                                    </div>
                                @endcan
                            </div>
                            <div class="mt-1.5 space-y-0.5 text-sm">
                                @if ($c->email) <a href="mailto:{{ $c->email }}" class="flex items-center gap-2 truncate text-slate-600 hover:text-indigo-700"><x-icon name="mail" class="size-3.5 shrink-0 text-slate-400" />{{ $c->email }}</a> @endif
                                @if ($c->phone) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->phone) }}" class="flex items-center gap-2 text-slate-600 hover:text-indigo-700"><x-icon name="phone" class="size-3.5 shrink-0 text-slate-400" />{{ $c->phone }}</a> @endif
                            </div>
                        </li>
                    @empty
                        <li><x-empty class="py-6">No contacts yet.</x-empty></li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    @endif

    {{-- ───────── Projects ───────── --}}
    @if ($tab === 'projects')
        <div class="flex justify-end">
            <a href="{{ route('projects.index', ['client' => $client->id, 'new' => 1]) }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> New project for {{ $client->name }}</a>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $p)
                <a wire:key="cp-{{ $p->id }}" href="{{ route('projects.show', $p) }}" wire:navigate class="rounded-xl border border-t-4 border-slate-200 bg-white p-4 hover:shadow-md {{ \App\Support\Palette::bar($p->color) }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ $p->name }}</p>
                        <x-badge :color="$p->status->badge()">{{ $p->status->label() }}</x-badge>
                    </div>
                    <div class="mt-3 flex justify-between text-xs text-slate-500"><span>{{ $p->completed_tasks_count }}/{{ $p->tasks_count }} tasks</span><span>{{ $p->progress() }}%</span></div>
                    <x-progress :value="$p->progress()" class="mt-1.5" />
                    <p class="mt-3 text-xs text-slate-500">{{ $p->deadline ? 'Due '.$p->deadline->format('d M Y') : 'No deadline' }}</p>
                </a>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white"><x-empty>No projects for {{ $client->name }} yet.</x-empty></div>
            @endforelse
        </div>
    @endif

    {{-- ───────── Links ───────── --}}
    @if ($tab === 'links')
        <x-card title="Links">
            <x-slot:actions>
                @can('update', $client)
                    <button wire:click="editLink" class="btn-primary py-1.5"><x-icon name="plus" class="size-4" /> Add link</button>
                @endcan
            </x-slot:actions>
            <ul class="divide-y divide-slate-100">
                @forelse ($client->links as $l)
                    <li wire:key="ln-{{ $l->id }}" class="group flex items-center gap-3 px-4 py-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg">{{ $l->icon() }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $l->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-700">{{ $l->label }} ↗</a>
                            <p class="truncate text-xs text-slate-500">{{ $l->typeLabel() }} · {{ $l->host() }}</p>
                        </div>
                        <button type="button" x-data="{ c: false }" x-on:click="navigator.clipboard.writeText(@js($l->url)); c = true; setTimeout(() => c = false, 1500)" class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100" x-text="c ? '✓ Copied' : 'Copy'">Copy</button>
                        @can('update', $client)
                            <button wire:click="editLink({{ $l->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit {{ $l->label }}"><x-icon name="pencil" class="size-4" /></button>
                            <button wire:click="deleteLink({{ $l->id }})" wire:confirm="Remove {{ $l->label }}?" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove {{ $l->label }}"><x-icon name="trash" class="size-4" /></button>
                        @endcan
                    </li>
                @empty
                    <li><x-empty>Add the client's website, staging site, admin panel, social pages, shared drives…</x-empty></li>
                @endforelse
            </ul>
        </x-card>
    @endif

    {{-- ───────── Password vault ───────── --}}
    @if ($tab === 'vault')
        <div x-data="{
                unlocked: @js($unlocked),
                asking: false,
                password: '',
                busy: false,
                shown: {},
                timers: {},
                async unlock() {
                    this.busy = true;
                    const ok = await $wire.unlockVault(this.password);
                    this.busy = false;
                    this.password = '';
                    if (ok) { this.unlocked = true; this.asking = false; }
                },
                async reveal(id) {
                    if (! this.unlocked) { this.asking = true; return; }
                    if (this.shown[id]) { delete this.shown[id]; return; }
                    const secret = await $wire.reveal(id);
                    if (! secret) { this.unlocked = false; this.asking = true; return; }
                    this.shown[id] = secret;
                    clearTimeout(this.timers[id]);
                    this.timers[id] = setTimeout(() => delete this.shown[id], 30000);
                },
                async copy(id) {
                    if (! this.unlocked) { this.asking = true; return; }
                    const secret = this.shown[id] ?? await $wire.reveal(id);
                    if (! secret) { this.unlocked = false; this.asking = true; return; }
                    await navigator.clipboard.writeText(secret.password);
                    $dispatch('notify', { message: 'Password copied. Clipboard clears in 30 seconds.' });
                    setTimeout(() => navigator.clipboard.writeText('').catch(() => {}), 30000);
                },
             }" class="space-y-4">
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
                <span class="flex size-10 items-center justify-center rounded-lg bg-slate-900 text-white"><x-icon name="shield" class="size-5" /></span>
                <div class="min-w-0 flex-1 text-sm">
                    <p class="font-semibold text-slate-900">Password vault</p>
                    <p class="text-slate-500">Encrypted at rest. Only super admins and admins can open it, after confirming their own password. Every reveal is recorded in the activity log.</p>
                </div>
                <template x-if="unlocked">
                    <div class="flex items-center gap-2">
                        <x-badge color="bg-emerald-100 text-emerald-700">🔓 Unlocked for 15 min</x-badge>
                        <button x-on:click="$wire.lockVault(); unlocked = false; shown = {}" class="btn-secondary py-1.5">Lock</button>
                    </div>
                </template>
                <template x-if="! unlocked">
                    <button x-on:click="asking = true" class="btn-secondary py-1.5">🔒 Unlock</button>
                </template>
                @can('viewCredentials', $client)
                    <button x-on:click="unlocked ? $wire.editCredential() : (asking = true)" class="btn-primary py-1.5"><x-icon name="plus" class="size-4" /> Add login</button>
                @endcan
            </div>

            {{-- Unlock prompt --}}
            <form x-show="asking && ! unlocked" x-cloak x-on:submit.prevent="unlock()" class="flex flex-wrap items-end gap-3 rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                <x-field label="Confirm your password to unlock" for="vault-pw" class="min-w-60 flex-1">
                    <input x-model="password" id="vault-pw" type="password" autocomplete="current-password" class="input" x-effect="asking && $nextTick(() => $el.focus())">
                </x-field>
                <button type="submit" class="btn-primary" :disabled="busy || ! password">Unlock</button>
                <button type="button" x-on:click="asking = false" class="btn-secondary">Cancel</button>
                @error('vault') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
            </form>

            <x-card>
                <ul class="divide-y divide-slate-100">
                    @forelse ($client->credentials as $cr)
                        <li wire:key="cr-{{ $cr->id }}" class="group px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100">🔑</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $cr->label }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        @if ($cr->username) <span class="font-mono">{{ $cr->username }}</span> @endif
                                        @if ($cr->url) · <a href="{{ $cr->url }}" target="_blank" rel="noopener noreferrer" class="hover:text-indigo-700">{{ parse_url($cr->url, PHP_URL_HOST) ?: $cr->url }} ↗</a> @endif
                                    </p>
                                </div>
                                <code class="min-w-32 rounded bg-slate-50 px-2 py-1 font-mono text-sm text-slate-800 ring-1 ring-slate-200" x-text="shown[{{ $cr->id }}]?.password ?? '••••••••••'"></code>
                                <button x-on:click="reveal({{ $cr->id }})" class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" x-text="shown[{{ $cr->id }}] ? 'Hide' : 'Reveal'">Reveal</button>
                                <button x-on:click="copy({{ $cr->id }})" class="rounded px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">Copy</button>
                                @if ($cr->username)
                                    <button type="button" x-data="{ c: false }" x-on:click="navigator.clipboard.writeText(@js($cr->username)); c = true; setTimeout(() => c = false, 1500)" class="rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100" x-text="c ? '✓' : 'Copy user'">Copy user</button>
                                @endif
                                <button x-on:click="unlocked ? $wire.editCredential({{ $cr->id }}) : (asking = true)" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit {{ $cr->label }}"><x-icon name="pencil" class="size-4" /></button>
                                <button wire:click="deleteCredential({{ $cr->id }})" wire:confirm="Delete the {{ $cr->label }} login? This cannot be undone." class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete {{ $cr->label }}"><x-icon name="trash" class="size-4" /></button>
                            </div>
                            <p x-show="shown[{{ $cr->id }}]?.notes" x-cloak class="mt-2 ml-12 rounded bg-amber-50 px-3 py-2 text-xs whitespace-pre-line text-amber-900" x-text="shown[{{ $cr->id }}]?.notes"></p>
                            <p class="mt-1 ml-12 text-[11px] text-slate-400">Added by {{ $cr->creator->name }} · updated {{ $cr->updated_at->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li><x-empty>No saved logins yet. Store hosting, CMS, domain, email and social logins here instead of in chats or spreadsheets.</x-empty></li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    @endif

    {{-- ───────── Files ───────── --}}
    @if ($tab === 'files')
        @can('update', $client)
            <form wire:submit="uploadFile" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_14rem_1fr_auto] sm:items-end">
                <x-field label="File" for="cf-file" error="upload">
                    <input wire:model="upload" id="cf-file" type="file" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                    <p wire:loading wire:target="upload" class="text-xs text-slate-500">Uploading…</p>
                </x-field>
                <x-field label="Type" for="cf-type" error="fileType">
                    <select wire:model="fileType" id="cf-type" class="input">
                        @foreach (\App\Models\ClientFile::TYPES as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Title (optional)" for="cf-title" error="fileTitle">
                    <input wire:model="fileTitle" id="cf-title" class="input" placeholder="e.g. 2026 service agreement">
                </x-field>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="upload,uploadFile">Upload</button>
                <p class="text-[11px] text-slate-400 sm:col-span-4">PDF, Office documents, images, ZIP, design files (AI, PSD, EPS) or video, up to 20 MB.</p>
            </form>
        @endcan
        <x-card>
            <ul class="divide-y divide-slate-100">
                @forelse ($client->files as $f)
                    <li wire:key="cf-{{ $f->id }}" class="flex items-center gap-3 px-4 py-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg">{{ str_contains((string) $f->mime_type, 'image') ? '🖼️' : (str_contains((string) $f->mime_type, 'pdf') ? '📕' : (str_contains((string) $f->mime_type, 'video') ? '🎬' : '📄')) }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('client-files.show', $f) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-700">{{ $f->title }}</a>
                            <p class="truncate text-xs text-slate-500">{{ $f->typeLabel() }} · {{ $f->humanSize() }} · {{ $f->uploader->name }} · {{ $f->created_at->format('d M Y') }}</p>
                        </div>
                        <a href="{{ route('client-files.show', $f) }}" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Download {{ $f->title }}"><x-icon name="download" class="size-4" /></a>
                        @can('update', $client)
                            <button wire:click="deleteFile({{ $f->id }})" wire:confirm="Delete “{{ $f->title }}”? The file is permanently removed." class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete {{ $f->title }}"><x-icon name="trash" class="size-4" /></button>
                        @endcan
                    </li>
                @empty
                    <li><x-empty>No files yet. Keep contracts, proposals, brand assets and content here.</x-empty></li>
                @endforelse
            </ul>
        </x-card>
    @endif

    {{-- ───────── Dialogs ───────── --}}
    @can('update', $client)
        <x-modal wire:model="showForm" title="Edit client" max-width="max-w-3xl">
            @include('livewire.clients._form')
        </x-modal>

        <x-modal wire:model="showContact" :title="$contactId ? 'Edit contact' : 'Add contact'" max-width="max-w-lg">
            <form wire:submit="saveContact" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Name" for="cc-name" error="contactName"><input wire:model="contactName" id="cc-name" class="input"></x-field>
                <x-field label="Role" for="cc-role" error="contactRole"><input wire:model="contactRole" id="cc-role" class="input" placeholder="Owner, Marketing head…"></x-field>
                <x-field label="Email" for="cc-email" error="contactEmail"><input wire:model="contactEmail" id="cc-email" type="email" class="input"></x-field>
                <x-field label="Phone" for="cc-phone" error="contactPhone"><input wire:model="contactPhone" id="cc-phone" type="tel" class="input"></x-field>
                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                    <input wire:model="contactPrimary" type="checkbox" class="rounded border-slate-300 text-indigo-600"> Main contact for this client
                </label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save contact</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="showLink" :title="$linkId ? 'Edit link' : 'Add link'" max-width="max-w-lg">
            <form wire:submit="saveLink" class="space-y-4">
                <x-field label="Type" for="cl-type" error="linkType">
                    <select wire:model="linkType" id="cl-type" class="input">
                        @foreach (\App\Models\ClientLink::TYPES as $value => [$label, $icon]) <option value="{{ $value }}">{{ $icon }} {{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Link" for="cl-url" error="linkUrl"><input wire:model="linkUrl" id="cl-url" type="url" class="input" placeholder="https://"></x-field>
                <x-field label="Label (optional)" for="cl-label" error="linkLabel"><input wire:model="linkLabel" id="cl-label" class="input" placeholder="e.g. Instagram page"></x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save link</button>
                </div>
            </form>
        </x-modal>
    @endcan

    @can('viewCredentials', $client)
        <x-modal wire:model="showCredential" :title="$credentialId ? 'Edit login' : 'Add login'" max-width="max-w-lg">
            <form wire:submit="saveCredential" class="space-y-4" x-data="{ show: false }">
                <x-field label="Name" for="cr-label" error="credLabel"><input wire:model="credLabel" id="cr-label" class="input" placeholder="WordPress admin, Hostinger, Instagram…"></x-field>
                <x-field label="Login URL" for="cr-url" error="credUrl"><input wire:model="credUrl" id="cr-url" type="url" class="input" placeholder="https://"></x-field>
                <x-field label="Username / email" for="cr-user" error="credUsername"><input wire:model="credUsername" id="cr-user" class="input font-mono" autocomplete="off"></x-field>
                <x-field :label="$credentialId ? 'New password (leave blank to keep the saved one)' : 'Password'" for="cr-pass" error="credPassword">
                    <div class="relative">
                        <input wire:model="credPassword" id="cr-pass" :type="show ? 'text' : 'password'" autocomplete="new-password" class="input pr-16 font-mono">
                        <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-3 text-xs text-slate-500" x-text="show ? 'Hide' : 'Show'">Show</button>
                    </div>
                </x-field>
                <x-field :label="$credentialId ? 'Secure notes (leave blank to keep existing)' : 'Secure notes (optional)'" for="cr-notes" error="credNotes">
                    <textarea wire:model="credNotes" id="cr-notes" rows="2" class="input" placeholder="2FA backup codes, security questions… (encrypted)"></textarea>
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save securely</button>
                </div>
            </form>
        </x-modal>
    @endcan
</div>
