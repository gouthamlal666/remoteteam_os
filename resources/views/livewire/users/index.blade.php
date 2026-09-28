<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 flex-wrap gap-2">
            <div class="relative w-full sm:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, email, title…" class="input pl-9">
            </div>
            @if ($actor->isManager())
                <select wire:model.live="roleFilter" class="input w-auto">
                    <option value="">All roles</option>
                    @foreach (\App\Enums\Role::cases() as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="departmentFilter" class="input w-auto">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <button wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Add person</button>
    </div>

    @php $isSuper = $actor->isManager(); @endphp
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        {{-- Column headings (desktop) --}}
        <div @class([
            'hidden border-b border-slate-100 bg-slate-50 px-5 py-2.5 text-xs font-medium tracking-wide text-slate-500 uppercase lg:grid lg:items-center lg:gap-4',
            'lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.6fr)_minmax(0,1.1fr)_minmax(0,1.1fr)_7rem]' => $isSuper,
            'lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.6fr)_minmax(0,1.1fr)_7rem]' => ! $isSuper,
        ])>
            <span>Person</span>
            <span>Contact</span>
            <span>Role &amp; team</span>
            @if ($isSuper) <span>Employee record</span> @endif
            <span class="text-right">Actions</span>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse ($users as $u)
                @php
                    $record = $isSuper ? $u->recordCompleteness() : null;
                    $recordUrl = route('users.show', $u).'#employee';
                @endphp
                <li wire:key="user-{{ $u->id }}" @class([
                    'grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-3 px-5 py-4 transition hover:bg-slate-50/70',
                    'lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.6fr)_minmax(0,1.1fr)_minmax(0,1.1fr)_7rem]' => $isSuper,
                    'lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.6fr)_minmax(0,1.1fr)_7rem]' => ! $isSuper,
                    'opacity-60' => ! $u->is_active,
                ])>
                    {{-- Person --}}
                    <a href="{{ route('users.show', $u) }}" wire:navigate class="group flex min-w-0 items-center gap-3">
                        <x-avatar :user="$u" size="size-11 text-sm" presence />
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900 group-hover:text-indigo-700">{{ $u->name }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $u->job_title ?: 'No job title' }}</p>
                            @if ($u->activeStatus())
                                <span class="mt-1 inline-flex max-w-full items-center truncate rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800 ring-1 ring-amber-200">{{ $u->activeStatus() }}</span>
                            @endif
                        </div>
                    </a>

                    {{-- Contact --}}
                    <div class="order-3 col-span-2 min-w-0 space-y-1 text-sm lg:order-none lg:col-span-1">
                        <p class="flex items-center gap-2 truncate text-slate-600"><x-icon name="mail" class="size-4 shrink-0 text-slate-400" /><span class="truncate">{{ $u->email }}</span></p>
                        @if ($isSuper)
                            @if ($u->phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $u->phone) }}" class="flex items-center gap-2 text-slate-600 hover:text-indigo-700"><x-icon name="phone" class="size-4 shrink-0 text-slate-400" />{{ $u->phone }}</a>
                            @else
                                <p class="flex items-center gap-2 text-slate-400"><x-icon name="phone" class="size-4 shrink-0" />No mobile yet</p>
                            @endif
                        @endif
                        <p class="flex items-center gap-2 text-xs text-slate-500"><x-local-time :user="$u" /></p>
                    </div>

                    {{-- Role & team --}}
                    <div class="order-4 col-span-2 flex flex-wrap items-center gap-2 lg:order-none lg:col-span-1 lg:block lg:space-y-1.5">
                        <x-badge :color="$u->role->badge()">{{ $u->role->label() }}</x-badge>
                        <p class="text-sm text-slate-600">{{ $u->department?->name ?? 'No department' }}</p>
                        @unless ($u->is_active) <x-badge color="bg-slate-200 text-slate-600">Deactivated</x-badge> @endunless
                    </div>

                    {{-- Employee record completeness --}}
                    @if ($isSuper)
                        <a href="{{ $recordUrl }}" class="group order-5 col-span-2 block rounded-lg p-2 -m-2 hover:bg-indigo-50 lg:order-none lg:col-span-1"
                           title="{{ $record['missing'] ? 'Missing: '.implode(', ', $record['missing']) : 'Complete' }}">
                            <div class="flex items-center justify-between text-xs">
                                <span @class(['font-medium', 'text-emerald-700' => $record['percent'] === 100, 'text-slate-700' => $record['percent'] < 100])>
                                    {{ $record['percent'] === 100 ? 'Complete' : $record['done'].' of '.$record['total'].' filled' }}
                                </span>
                                <span class="text-indigo-600 opacity-0 transition group-hover:opacity-100">Open →</span>
                            </div>
                            <x-progress :value="$record['percent']" :color="$record['percent'] === 100 ? 'bg-emerald-500' : ($record['percent'] >= 50 ? 'bg-indigo-500' : 'bg-amber-400')" class="mt-1.5 h-1.5" />
                            @if ($record['missing'])
                                <p class="mt-1 truncate text-[11px] text-slate-400">Needs {{ strtolower(implode(', ', array_slice($record['missing'], 0, 2))) }}{{ count($record['missing']) > 2 ? ' +'.(count($record['missing']) - 2) : '' }}</p>
                            @endif
                        </a>
                    @endif

                    {{-- Actions --}}
                    <div class="order-2 flex justify-end lg:order-none" x-data="{ open: false }" x-on:keydown.escape="open = false">
                        <button type="button" x-ref="trigger" x-on:click="open = !open"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50"
                                :aria-expanded="open" aria-haspopup="menu">
                            Manage
                            <svg class="size-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                        </button>
                        <template x-teleport="body">
                            <div x-show="open" x-cloak x-anchor.bottom-end.offset.6="$refs.trigger" x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false" x-transition.origin.top.right
                                 class="z-50 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl" role="menu">
                                <a href="{{ route('users.show', $u) }}" wire:navigate class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50" role="menuitem">
                                    <x-icon name="user" class="mt-0.5 size-5 text-slate-400" />
                                    <span><span class="block text-sm font-medium text-slate-900">View profile</span><span class="block text-xs text-slate-500">Work, projects and performance</span></span>
                                </a>
                                @can('manageEmployeeRecord', $u)
                                    <a href="{{ $recordUrl }}" class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50" role="menuitem">
                                        <x-icon name="id-card" class="mt-0.5 size-5 text-indigo-500" />
                                        <span><span class="block text-sm font-medium text-slate-900">Employee record</span><span class="block text-xs text-slate-500">Photo, mobile, guardian contact, documents</span></span>
                                    </a>
                                @endcan
                                @can('update', $u)
                                    <button type="button" wire:click="edit({{ $u->id }})" x-on:click="open = false" class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-slate-50" role="menuitem">
                                        <x-icon name="pencil" class="mt-0.5 size-5 text-slate-400" />
                                        <span><span class="block text-sm font-medium text-slate-900">Edit account</span><span class="block text-xs text-slate-500">Name, email, role, department, password</span></span>
                                    </button>
                                @endcan
                                @if (auth()->user()->can('messageUser', [\App\Models\Conversation::class, $u]))
                                    <a href="{{ route('chat.with', $u) }}" class="flex items-start gap-3 px-4 py-2.5 hover:bg-slate-50" role="menuitem">
                                        <x-icon name="chat" class="mt-0.5 size-5 text-slate-400" />
                                        <span><span class="block text-sm font-medium text-slate-900">Send message</span><span class="block text-xs text-slate-500">Open a private chat</span></span>
                                    </a>
                                @endif
                                @can('delete', $u)
                                    <div class="my-1.5 border-t border-slate-100"></div>
                                    <button type="button" wire:click="delete({{ $u->id }})" x-on:click="open = false"
                                            wire:confirm="Permanently delete {{ $u->name }}?\n\nProjects, tasks and meetings they own will be transferred to you. Their comments, messages and files stay as &quot;Deleted user&quot;. Their employee record, daily reports and direct messages are removed.\n\nTo keep everything, deactivate them instead (Edit account → untick Active)."
                                            class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-red-50" role="menuitem">
                                        <x-icon name="trash" class="mt-0.5 size-5 text-red-500" />
                                        <span><span class="block text-sm font-medium text-red-600">Delete person</span><span class="block text-xs text-slate-500">Permanently remove their account</span></span>
                                    </button>
                                @endcan
                            </div>
                        </template>
                    </div>
                </li>
            @empty
                <li><x-empty>No people match your filters.</x-empty></li>
            @endforelse
        </ul>
    </div>
    {{ $users->links() }}

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit account' : 'Add person'">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <p class="-mt-1 text-sm text-slate-500 sm:col-span-2">{{ $editingId ? 'Sign-in and access settings.' : 'Create their sign-in account.' }} Personal details like photo, mobile and guardian contact live in the <strong class="font-medium text-slate-700">employee record</strong>.</p>
            <x-field label="Full name" for="u-name" error="name">
                <input wire:model="name" id="u-name" class="input">
            </x-field>
            <x-field label="Email" for="u-email" error="email">
                <input wire:model="email" id="u-email" type="email" class="input">
            </x-field>
            <x-field label="Role" for="u-role" error="role">
                <select wire:model="role" id="u-role" class="input" @disabled($editingId === $actor->id)>
                    @foreach ($roles as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Department" for="u-dept" error="department_id">
                <select wire:model="department_id" id="u-dept" class="input">
                    <option value="">— None —</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Job title" for="u-title" error="job_title">
                <input wire:model="job_title" id="u-title" class="input" placeholder="Laravel Developer">
            </x-field>
            <x-field :label="$editingId ? 'New password (optional)' : 'Password'" for="u-pass" error="password">
                <div x-data="{
                        show: false,
                        copied: '',
                        generate() {
                            // Browser-side crypto RNG; guarantees upper, lower, digit and symbol.
                            const sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '!@#$%&*?'];
                            const all = sets.join('');
                            const rand = n => { const a = new Uint32Array(1); crypto.getRandomValues(a); return a[0] % n };
                            const chars = sets.map(set => set[rand(set.length)]);
                            while (chars.length < {{ max(14, \App\Support\Settings::minPasswordLength()) }}) chars.push(all[rand(all.length)]);
                            for (let i = chars.length - 1; i > 0; i--) { const j = rand(i + 1); [chars[i], chars[j]] = [chars[j], chars[i]] }
                            $wire.set('password', chars.join(''), false);
                            this.show = true;
                            this.copied = '';
                        },
                        async copy(text, what) {
                            await navigator.clipboard.writeText(text);
                            this.copied = what;
                            setTimeout(() => this.copied = '', 2000);
                        },
                        details() {
                            return `Hi ${$wire.name || ''},\n\nYour RemoteTeam OS account is ready.\n\nLogin: {{ route('login') }}\nEmail: ${$wire.email}\nPassword: ${$wire.password}\n\nPlease change your password after signing in (Settings → Change password).`;
                        },
                     }">
                    <div class="relative">
                        <input wire:model="password" id="u-pass" :type="show ? 'text' : 'password'" autocomplete="new-password" class="input pr-10 font-mono" placeholder="{{ $editingId ? 'Leave blank to keep current' : 'Type or generate' }}">
                        <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" :aria-label="show ? 'Hide password' : 'Show password'">
                            <svg x-show="! show" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg x-show="show" x-cloak class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        <button type="button" x-on:click="generate()" class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-800">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            Generate strong password
                        </button>
                        <template x-if="$wire.password">
                            <span class="flex items-center gap-3">
                                <button type="button" x-on:click="copy($wire.password, 'password')" class="font-medium text-slate-600 hover:text-slate-900" x-text="copied === 'password' ? '✓ Copied' : 'Copy'"></button>
                                <button type="button" x-on:click="copy(details(), 'details')" class="font-medium text-slate-600 hover:text-slate-900" x-text="copied === 'details' ? '✓ Login details copied' : 'Copy login details'"></button>
                            </span>
                        </template>
                    </div>
                    @unless ($editingId)
                        <p x-show="$wire.password" x-cloak class="mt-1 text-[11px] text-amber-700">Copy it before saving: it can't be shown again afterwards.</p>
                    @endunless
                </div>
            </x-field>
            <x-field label="Skills (comma separated)" for="u-skills" error="skills" class="sm:col-span-2">
                <input wire:model="skills" id="u-skills" class="input" placeholder="Laravel, PHP, MySQL">
            </x-field>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                <input wire:model="is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" @disabled($editingId === $actor->id)>
                Active (can sign in)
            </label>
            @if ($editingId && $actor->isManager())
                <a href="{{ route('users.show', $editingId) }}#employee" class="group flex items-center gap-3 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-white p-3 hover:border-indigo-300 sm:col-span-2">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-xs ring-1 ring-indigo-100"><x-icon name="id-card" class="size-5" /></span>
                    <span class="min-w-0 flex-1 text-sm">
                        <span class="block font-semibold text-slate-900">Open employee record</span>
                        <span class="text-slate-500">Photo, mobile, guardian contact, address and documents</span>
                    </span>
                    <span class="text-indigo-600 transition group-hover:translate-x-0.5">→</span>
                </a>
            @elseif (! $editingId && $actor->isManager())
                <p class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 sm:col-span-2"><x-icon name="id-card" class="size-5 text-indigo-500" /> Next, you'll add their photo, mobile, guardian contact and documents.</p>
            @endif
            <div class="flex justify-end gap-2 pt-2 sm:col-span-2">
                <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</div>
