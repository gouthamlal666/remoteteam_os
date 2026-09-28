@php
    $emojiPicker = ['😀','😂','😊','😍','🤔','😅','😎','🙏','👍','👎','👏','🙌','🔥','🎉','❤️','✅','❌','👀','🚀','💡','⏰','📌','📎','☕'];
@endphp
<div class="flex h-[calc(100dvh-7rem)] overflow-hidden rounded-xl border border-slate-200 bg-white sm:h-[calc(100dvh-8rem)]"
     x-data="{ list: {{ $conversation ? 'false' : 'true' }} }">

    {{-- Conversation list --}}
    <aside :class="list ? 'flex' : 'hidden md:flex'" class="w-full shrink-0 flex-col border-r border-slate-200 md:w-72">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Conversations</h2>
            <div class="flex gap-1">
                @can('createChannel', \App\Models\Conversation::class)
                    <button wire:click="$set('showNewChannel', true)" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" title="New channel"># New</button>
                @endcan
                <button wire:click="$set('showNewDirect', true)" class="rounded-md p-1 text-slate-600 hover:bg-slate-100" title="New direct message"><x-icon name="pencil" class="size-4" /></button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-2" wire:poll.10s.visible>
            @foreach (['Channels' => $channels, 'Direct messages' => $directs] as $heading => $items)
                <p class="px-2 pt-3 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $heading }}</p>
                @forelse ($items as $c)
                    @php $active = $conversation?->id === $c->id; @endphp
                    <button wire:key="conv-{{ $c->id }}" wire:click="open({{ $c->id }})" x-on:click="list = false"
                            @class(['flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left', 'bg-indigo-50' => $active, 'hover:bg-slate-50' => ! $active])>
                        @if ($c->isDirect() && $c->otherMember($user))
                            <x-avatar :user="$c->otherMember($user)" size="size-8" presence />
                        @else
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm font-semibold text-slate-500">#</span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span @class(['block truncate text-sm', 'font-semibold text-slate-900' => $c->unread_count, 'text-slate-700' => ! $c->unread_count])>{{ $c->isDirect() ? $c->displayName($user) : $c->name }}</span>
                            <span class="block truncate text-xs text-slate-400">
                                @if ($c->latestMessage)
                                    {{ $c->latestMessage->user_id === $user->id ? 'You' : \Illuminate\Support\Str::before($c->latestMessage->user->name, ' ') }}:
                                    {{ $c->latestMessage->body ? \Illuminate\Support\Str::limit($c->latestMessage->body, 40) : '📎 Attachment' }}
                                @else
                                    No messages yet
                                @endif
                            </span>
                        </span>
                        @if ($c->unread_count)
                            <span class="rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $c->unread_count }}</span>
                        @endif
                    </button>
                @empty
                    <p class="px-2 py-1 text-xs text-slate-400">None yet.</p>
                @endforelse
            @endforeach
        </div>
    </aside>

    {{-- Active conversation --}}
    <section :class="list ? 'hidden md:flex' : 'flex'" class="min-w-0 flex-1 flex-col">
        @if ($conversation)
            <header class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5">
                <button x-on:click="list = true" class="rounded-md p-1 text-slate-500 hover:bg-slate-100 md:hidden" aria-label="Back"><x-icon name="menu" class="size-5" /></button>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-slate-900">{{ $conversation->displayName($user) }}</p>
                    <p class="truncate text-xs text-slate-500">
                        @if ($conversation->isDirect() && ($other = $conversation->otherMember($user)))
                            {{ $other->isOnline() ? '🟢 Online' : 'Offline' }}
                            @if ($other->activeStatus()) · {{ $other->activeStatus() }} @endif
                            · <x-local-time :user="$other" />
                        @else
                            {{ $members->count() }} members{{ $conversation->description ? ' · '.$conversation->description : '' }}
                        @endif
                    </p>
                </div>
                <div x-data="{ searching: @js($search !== '') }" class="flex items-center gap-1">
                    <input x-show="searching" x-cloak wire:model.live.debounce.300ms="search" type="search" placeholder="Search messages…" class="input w-40 py-1.5 sm:w-56" x-ref="s" x-effect="searching && $nextTick(() => $refs.s.focus())">
                    <button x-on:click="searching = !searching; if (!searching) $wire.set('search', '')" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100" title="Search"><x-icon name="search" class="size-5" /></button>
                    <button wire:click="$toggle('showPinned')" @class(['rounded-md p-1.5 hover:bg-slate-100', 'text-indigo-600' => $showPinned, 'text-slate-500' => ! $showPinned]) title="Pinned messages">📌<span class="text-xs">{{ $pinned->count() ?: '' }}</span></button>
                    @unless ($conversation->isDirect())
                        <button wire:click="$set('showMembers', true)" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100" title="Members"><x-icon name="users" class="size-5" /></button>
                    @endunless
                </div>
            </header>

            @if ($showPinned)
                <div class="max-h-48 overflow-y-auto border-b border-amber-100 bg-amber-50/60 px-4 py-2">
                    <p class="mb-1 text-xs font-semibold text-amber-800">Pinned messages</p>
                    @forelse ($pinned as $p)
                        <div class="flex items-start justify-between gap-2 py-1 text-sm">
                            <p class="min-w-0 text-slate-700"><span class="font-medium">{{ $p->user->name }}:</span> {{ \Illuminate\Support\Str::limit($p->body ?? '📎 Attachment', 140) }}</p>
                            <button wire:click="togglePin({{ $p->id }})" class="shrink-0 text-xs text-slate-400 hover:text-slate-700">Unpin</button>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nothing pinned yet. Hover a message and click 📌.</p>
                    @endforelse
                </div>
            @endif

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto px-4 py-4" wire:poll.4s.visible="poll"
                 x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                 x-on:chat-scroll.window="setTimeout(() => $el.scrollTop = $el.scrollHeight, 50)">
                @if ($hasOlder)
                    <div class="mb-4 text-center"><button wire:click="loadOlder" class="text-xs font-medium text-indigo-600 hover:underline">Load older messages</button></div>
                @endif
                @if ($search && $messages->isEmpty())
                    <x-empty>No messages match “{{ $search }}”.</x-empty>
                @elseif ($messages->isEmpty())
                    <x-empty>Say hello 👋 This is the start of {{ $conversation->displayName($user) }}.</x-empty>
                @endif

                @php $previous = null; $canModerate = $user->can('manage', $conversation); @endphp
                @foreach ($messages as $m)
                    @php
                        $grouped = $previous && $previous->user_id === $m->user_id && $m->created_at->diffInMinutes($previous->created_at, true) < 5 && ! $m->reply_to_id;
                        $newDay = ! $previous || ! $previous->created_at->isSameDay($m->created_at);
                        $previous = $m;
                    @endphp
                    @if ($newDay)
                        <div class="my-4 flex items-center gap-3 text-xs text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>{{ $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('l, d M')) }}<span class="h-px flex-1 bg-slate-200"></span></div>
                    @endif
                    <div wire:key="msg-{{ $m->id }}" @class(['group relative flex gap-3 rounded-lg px-2 hover:bg-slate-50', 'mt-3 py-1' => ! $grouped, 'py-0.5' => $grouped, 'bg-amber-50/50' => $m->pinned_at])>
                        <div class="w-8 shrink-0">
                            @unless ($grouped) <x-avatar :user="$m->user" /> @endunless
                        </div>
                        <div class="min-w-0 flex-1">
                            @unless ($grouped)
                                <p class="text-sm"><span class="font-semibold text-slate-900">{{ $m->user->name }}</span> <span class="text-xs text-slate-400">{{ $m->created_at->format('h:i A') }}</span> @if ($m->pinned_at) <span class="text-xs">📌</span> @endif</p>
                            @endunless
                            @if ($m->replyTo)
                                <div class="mt-1 mb-1 border-l-2 border-slate-300 pl-2 text-xs text-slate-500"><span class="font-medium">{{ $m->replyTo->user->name }}</span>: {{ \Illuminate\Support\Str::limit($m->replyTo->body ?? '📎 Attachment', 100) }}</div>
                            @endif
                            @if ($m->body)
                                <div class="text-sm break-words text-slate-800">{{ $m->renderedBody() }}</div>
                            @endif
                            @foreach ($m->attachments as $a)
                                @if ($a->isImage())
                                    <a href="{{ route('chat.attachments.show', $a) }}" target="_blank" class="mt-2 block w-fit"><img src="{{ route('chat.attachments.show', $a) }}" alt="{{ $a->original_name }}" class="max-h-60 max-w-xs rounded-lg border border-slate-200 object-cover" loading="lazy"></a>
                                @else
                                    <a href="{{ route('chat.attachments.show', $a) }}" class="mt-2 flex w-fit max-w-xs items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:border-indigo-300">
                                        <x-icon name="paperclip" class="size-4 text-slate-400" />
                                        <span class="truncate font-medium text-slate-700">{{ $a->original_name }}</span>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $a->humanSize() }}</span>
                                    </a>
                                @endif
                            @endforeach
                            @if ($m->reactions->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($m->reactions->groupBy('emoji') as $emoji => $rs)
                                        @php $mine = $rs->contains('user_id', $user->id); @endphp
                                        <button wire:click="react({{ $m->id }}, '{{ $emoji }}')" title="{{ $rs->pluck('user.name')->join(', ') }}"
                                                @class(['rounded-full border px-1.5 py-0.5 text-xs', 'border-indigo-300 bg-indigo-50' => $mine, 'border-slate-200 bg-white' => ! $mine])>{{ $emoji }} {{ $rs->count() }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Hover actions --}}
                        <div class="absolute -top-3 right-2 hidden items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5 shadow-sm group-hover:flex">
                            @foreach (\App\Livewire\Chat\Index::REACTIONS as $emoji)
                                <button wire:click="react({{ $m->id }}, '{{ $emoji }}')" class="rounded px-1 text-sm hover:bg-slate-100">{{ $emoji }}</button>
                            @endforeach
                            <span class="mx-0.5 h-4 w-px bg-slate-200"></span>
                            <button wire:click="startReply({{ $m->id }})" class="rounded px-1.5 text-xs text-slate-600 hover:bg-slate-100" title="Reply">↩ Reply</button>
                            <button wire:click="togglePin({{ $m->id }})" class="rounded px-1 text-sm hover:bg-slate-100" title="{{ $m->pinned_at ? 'Unpin' : 'Pin' }}">📌</button>
                            @if ($m->user_id === $user->id || $canModerate)
                                <button wire:click="deleteMessage({{ $m->id }})" wire:confirm="Delete this message?" class="rounded px-1 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Delete"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Composer --}}
            <form wire:submit="send" class="border-t border-slate-100 p-3"
                  x-data="{
                      emoji: false,
                      mentions: @js($mentionables),
                      query: null,
                      get matches() { return this.query === null ? [] : this.mentions.filter(m => m.handle.includes(this.query) || m.name.toLowerCase().includes(this.query)).slice(0, 6) },
                      detect(el) {
                          const before = el.value.slice(0, el.selectionStart);
                          const m = before.match(/(?:^|\s)@([a-z0-9._-]*)$/i);
                          this.query = m ? m[1].toLowerCase() : null;
                      },
                      pick(handle) {
                          const el = this.$refs.input;
                          const pos = el.selectionStart;
                          const before = el.value.slice(0, pos).replace(/@([a-z0-9._-]*)$/i, '@' + handle + ' ');
                          el.value = before + el.value.slice(pos);
                          $wire.set('body', el.value, false);
                          this.query = null;
                          el.focus();
                      },
                      insert(text) {
                          const el = this.$refs.input;
                          const pos = el.selectionStart ?? el.value.length;
                          el.value = el.value.slice(0, pos) + text + el.value.slice(pos);
                          $wire.set('body', el.value, false);
                          this.emoji = false;
                          el.focus();
                      }
                  }">
                @if ($replyMessage)
                    <div class="mb-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                        <span class="truncate">Replying to <span class="font-medium">{{ $replyMessage->user->name }}</span>: {{ \Illuminate\Support\Str::limit($replyMessage->body ?? '📎 Attachment', 80) }}</span>
                        <button type="button" wire:click="$set('replyTo', null)" class="text-slate-400 hover:text-slate-700"><x-icon name="x" class="size-4" /></button>
                    </div>
                @endif
                @if ($files)
                    <div class="mb-2 flex flex-wrap gap-2">
                        @foreach ($files as $i => $file)
                            <span class="flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs">
                                {{ \Illuminate\Support\Str::limit($file->getClientOriginalName(), 30) }}
                                <button type="button" wire:click="removeFile({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="x" class="size-3.5" /></button>
                            </span>
                        @endforeach
                    </div>
                @endif
                <div class="relative flex items-end gap-2">
                    {{-- Mention suggestions --}}
                    <div x-show="matches.length" x-cloak class="absolute bottom-full left-10 z-10 mb-2 w-64 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                        <template x-for="m in matches" :key="m.handle">
                            <button type="button" x-on:click="pick(m.handle)" class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50">
                                <span x-text="m.name" class="text-slate-800"></span><span x-text="'@' + m.handle" class="text-xs text-slate-400"></span>
                            </button>
                        </template>
                    </div>
                    {{-- Emoji picker --}}
                    <div x-show="emoji" x-cloak x-on:click.outside="emoji = false" class="absolute bottom-full left-0 z-10 mb-2 grid w-64 grid-cols-8 gap-1 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                        @foreach ($emojiPicker as $e)
                            <button type="button" x-on:click="insert('{{ $e }}')" class="rounded p-1 text-lg hover:bg-slate-100">{{ $e }}</button>
                        @endforeach
                    </div>

                    <button type="button" x-on:click.stop="emoji = !emoji" class="rounded-lg p-2 text-lg hover:bg-slate-100" title="Emoji">😊</button>
                    <label class="cursor-pointer rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Attach files">
                        <input type="file" wire:model="files" multiple class="sr-only">
                        <x-icon name="paperclip" class="size-5" />
                    </label>
                    <textarea x-ref="input" wire:model="body" rows="1" placeholder="Message {{ $conversation->displayName($user) }} — @ to mention"
                              x-on:input="detect($el); $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                              x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); if (matches.length) { pick(matches[0].handle) } else { $wire.set('body', $el.value, false); $wire.send().then(() => { $el.style.height = 'auto' }) } }"
                              x-on:keydown.escape="query = null"
                              class="input max-h-40 flex-1 resize-none"></textarea>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="send,files">Send</button>
                </div>
                <div wire:loading wire:target="files" class="mt-1 text-xs text-slate-500">Uploading…</div>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </form>
        @else
            <div class="flex flex-1 items-center justify-center p-6">
                <x-empty>
                    <p class="font-medium text-slate-700">No conversation selected</p>
                    <p class="mt-1">Pick a channel or start a direct message.</p>
                </x-empty>
            </div>
        @endif
    </section>

    {{-- New direct message --}}
    <x-modal wire:model="showNewDirect" title="New direct message" max-width="max-w-md">
        <div x-data="{ q: '' }">
            <input x-model="q" type="search" placeholder="Search people…" class="input" autofocus>
            <ul class="mt-3 max-h-80 overflow-y-auto">
                @foreach ($people as $p)
                    <li wire:key="dm-{{ $p->id }}" x-show="@js(strtolower($p->name.' '.$p->job_title)).includes(q.toLowerCase())">
                        <button wire:click="startDirect({{ $p->id }})" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-slate-50">
                            <x-avatar :user="$p" />
                            <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-slate-900">{{ $p->name }}</span><span class="block text-xs text-slate-500">{{ $p->job_title }} · {{ $p->role->label() }}</span></span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-modal>

    {{-- New channel --}}
    @can('createChannel', \App\Models\Conversation::class)
        <x-modal wire:model="showNewChannel" title="New team channel" max-width="max-w-lg">
            <form wire:submit="createChannel" class="space-y-4">
                <x-field label="Channel name" for="ch-name" error="channelName">
                    <input wire:model="channelName" id="ch-name" class="input" placeholder="Development Team">
                </x-field>
                <x-field label="Description" for="ch-desc" error="channelDescription">
                    <input wire:model="channelDescription" id="ch-desc" class="input" placeholder="What's this channel for?">
                </x-field>
                <x-field label="Members" error="channelMembers">
                    <div x-data="{ q: '' }" class="rounded-lg border border-slate-300">
                        <input x-model="q" type="search" placeholder="Filter people…" class="w-full rounded-t-lg border-0 border-b border-slate-200 px-3 py-2 text-sm focus:ring-0 focus:outline-none">
                        <div class="max-h-52 overflow-y-auto p-1">
                            @foreach ($people as $p)
                                <label wire:key="chm-{{ $p->id }}" x-show="@js(strtolower($p->name.' '.$p->job_title)).includes(q.toLowerCase())" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50">
                                    <input type="checkbox" wire:model="channelMembers" value="{{ $p->id }}" class="rounded border-slate-300 text-indigo-600">
                                    {{ $p->name }} <span class="text-xs text-slate-400">{{ $p->job_title }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Create channel</button>
                </div>
            </form>
        </x-modal>
    @endcan

    {{-- Channel members --}}
    @if ($conversation && ! $conversation->isDirect())
        <x-modal wire:model="showMembers" :title="'Members of '.$conversation->name" max-width="max-w-md">
            @php $canManage = $user->can('manage', $conversation); @endphp
            <ul class="max-h-72 space-y-1 overflow-y-auto">
                @foreach ($members as $member)
                    <li wire:key="mem-{{ $member->id }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5">
                        <x-avatar :user="$member" presence />
                        <span class="min-w-0 flex-1 text-sm"><span class="font-medium text-slate-900">{{ $member->name }}</span> <span class="text-xs text-slate-400">{{ '@'.$member->handle() }}</span></span>
                        @if ($member->id !== $user->id)
                            <button wire:click="startDirect({{ $member->id }})" class="text-xs text-indigo-600 hover:underline">Message</button>
                            @if ($canManage)
                                <button wire:click="removeMember({{ $member->id }})" class="text-xs text-slate-400 hover:text-red-600">Remove</button>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($canManage)
                @php $outside = $people->whereNotIn('id', $members->pluck('id')); @endphp
                @if ($outside->isNotEmpty())
                    <form wire:submit="addMembers" class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                        <select wire:model="addMemberIds" multiple class="input h-24 flex-1">
                            @foreach ($outside as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-secondary self-start">Add</button>
                    </form>
                @endif
            @endif
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                @if ($canManage)
                    <button wire:click="deleteChannel" wire:confirm="Delete #{{ $conversation->name }} and all of its messages and files for everyone? This cannot be undone." class="btn-danger py-1.5"><x-icon name="trash" class="size-4" /> Delete channel</button>
                @else
                    <span></span>
                @endif
                <button wire:click="leave" wire:confirm="Leave this channel?" class="text-sm text-red-600 hover:underline">Leave channel</button>
            </div>
        </x-modal>
    @endif
</div>
