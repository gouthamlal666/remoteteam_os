@php
    [$statusLabel, $statusBadge] = $meeting->displayStatus();
    $responseBadge = ['accepted' => 'bg-emerald-100 text-emerald-700', 'declined' => 'bg-red-100 text-red-700', 'pending' => 'bg-slate-100 text-slate-600'];
    $joinable = $meeting->meeting_link && $meeting->status === 'scheduled' && now()->gte($meeting->starts_at->copy()->subMinutes(15)) && now()->lte($meeting->endsAt());
@endphp
<div>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('meetings.index') }}" wire:navigate class="text-slate-500 hover:text-slate-700">Meetings</a>
                <span class="text-slate-300">/</span>
                <x-badge :color="$statusBadge">{{ $statusLabel }}</x-badge>
                @if ($meeting->project)
                    <a href="{{ route('projects.show', $meeting->project) }}" wire:navigate><x-badge color="bg-indigo-50 text-indigo-700">{{ $meeting->project->name }}</x-badge></a>
                @endif
            </div>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $meeting->title }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ $meeting->starts_at->format('l, d F Y') }} · {{ $meeting->starts_at->format('h:i A') }} – {{ $meeting->endsAt()->format('h:i A') }}
                ({{ $meeting->duration_minutes }} min) · Organised by {{ $meeting->organizer->name }}
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                @if ($meeting->meeting_link && $meeting->status === 'scheduled')
                    @if ($joinable)
                        <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener" class="btn-success">Join meeting</a>
                    @else
                        <a href="{{ $meeting->meeting_link }}" target="_blank" rel="noopener" class="btn-secondary" title="Opens 15 minutes before the start">Meeting link</a>
                    @endif
                @endif
                @if ($me && $meeting->isUpcoming() && $me->id !== $meeting->organizer_id)
                    <span class="ml-1 text-sm text-slate-500">Going?</span>
                    <button wire:click="respond('accepted')" @class(['btn px-3 py-1.5', 'bg-emerald-600 text-white' => $me->pivot->response === 'accepted', 'btn-secondary' => $me->pivot->response !== 'accepted'])>Yes</button>
                    <button wire:click="respond('declined')" @class(['btn px-3 py-1.5', 'bg-red-600 text-white' => $me->pivot->response === 'declined', 'btn-secondary' => $me->pivot->response !== 'declined'])>No</button>
                @endif
                @if ($canManage && $meeting->status === 'scheduled')
                    <button wire:click="edit" class="btn-secondary ml-auto"><x-icon name="pencil" class="size-4" /> Edit</button>
                    <button wire:click="cancel" wire:confirm="Cancel this meeting for everyone? It stays in history as cancelled." class="btn-secondary text-red-600 hover:bg-red-50">Cancel meeting</button>
                @endif
                @can('delete', $meeting)
                    <button wire:click="delete" wire:confirm="Permanently delete “{{ $meeting->title }}”, its notes and attendance?{{ $meeting->isUpcoming() ? '\\nParticipants will be told it is cancelled.' : '' }}"
                            @class(['btn-secondary text-red-600 hover:bg-red-50', 'ml-auto' => $meeting->status !== 'scheduled']) aria-label="Delete meeting"><x-icon name="trash" class="size-4" /></button>
                @endcan
            </div>
        </div>

        <x-card title="Agenda">
            <div class="p-4 text-sm whitespace-pre-line text-slate-700">{{ $meeting->agenda ?: 'No agenda yet.' }}</div>
        </x-card>

        <x-card title="Notes">
            @if ($canManage && $meeting->status !== 'cancelled')
                <div class="space-y-3 p-4">
                    <textarea wire:model="notes" rows="6" class="input" placeholder="Decisions, action items, owners…"></textarea>
                    @error('notes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end"><button wire:click="saveNotes" class="btn-secondary">Save notes</button></div>
                </div>
            @else
                <div class="p-4 text-sm whitespace-pre-line text-slate-700">{{ $meeting->notes ?: 'No notes yet.' }}</div>
            @endif
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Participants ({{ $meeting->participants->count() }})">
            @if ($canManage && $meeting->status === 'scheduled' && $meeting->starts_at->isPast())
                <form wire:submit="complete">
                    <p class="px-4 pt-3 text-xs text-slate-500">Tick who attended, then mark the meeting as completed.</p>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($meeting->participants as $p)
                            <li wire:key="att-{{ $p->id }}">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5">
                                    <input type="checkbox" wire:model="attendance.{{ $p->id }}" class="rounded border-slate-300 text-emerald-600">
                                    <x-avatar :user="$p" size="size-7" />
                                    <span class="flex-1 text-sm text-slate-800">{{ $p->name }}</span>
                                    <x-badge :color="$responseBadge[$p->pivot->response]">{{ ucfirst($p->pivot->response) }}</x-badge>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-slate-100 p-3"><button type="submit" class="btn-success w-full">Mark as completed</button></div>
                </form>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($meeting->participants as $p)
                        <li wire:key="part-{{ $p->id }}" class="group flex items-center gap-3 px-4 py-2.5">
                            <x-avatar :user="$p" size="size-7" />
                            <span class="min-w-0 flex-1 text-sm"><span class="text-slate-800">{{ $p->name }}</span> <span class="text-xs text-slate-400">{{ $p->job_title }}</span></span>
                            @if ($meeting->status === 'completed')
                                @if ($p->pivot->attended)
                                    <x-badge color="bg-emerald-100 text-emerald-700">Attended</x-badge>
                                @else
                                    <x-badge color="bg-red-50 text-red-600">Absent</x-badge>
                                @endif
                            @else
                                <x-badge :color="$responseBadge[$p->pivot->response]">{{ $p->id === $meeting->organizer_id ? 'Organizer' : ucfirst($p->pivot->response) }}</x-badge>
                            @endif
                            @if ($canManage && $p->id !== $meeting->organizer_id && $meeting->status === 'scheduled')
                                <button wire:click="removeParticipant({{ $p->id }})" wire:confirm="Remove {{ $p->name }} from this meeting?" class="invisible rounded p-0.5 text-slate-400 group-hover:visible hover:text-red-600" aria-label="Remove {{ $p->name }}"><x-icon name="x" class="size-4" /></button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>

@if ($canManage)
    <x-modal wire:model="showForm" title="Edit meeting">
        @include('livewire.meetings._form')
    </x-modal>
@endif
</div>
