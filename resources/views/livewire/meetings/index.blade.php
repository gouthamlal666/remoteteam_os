<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach (['upcoming' => 'Upcoming', 'calendar' => 'Calendar', 'history' => 'History'] as $value => $label)
                <button wire:click="$set('view', '{{ $value }}')" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-indigo-600 text-white' => $view === $value, 'text-slate-600 hover:bg-slate-50' => $view !== $value])>{{ $label }}</button>
            @endforeach
        </div>
        @can('create', \App\Models\Meeting::class)
            <button wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> Schedule meeting</button>
        @endcan
    </div>

    @if ($view === 'calendar')
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <button wire:click="shiftMonth(-1)" class="btn-secondary px-2.5 py-1">‹</button>
                <h2 class="font-semibold text-slate-900">{{ $monthStart->format('F Y') }}</h2>
                <button wire:click="shiftMonth(1)" class="btn-secondary px-2.5 py-1">›</button>
            </div>
            <div class="overflow-x-auto">
                <div class="grid min-w-[700px] grid-cols-7 text-sm">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                        <div class="border-b border-slate-100 px-2 py-2 text-center text-xs font-medium text-slate-500">{{ $d }}</div>
                    @endforeach
                    @foreach ($days as $day)
                        @php $items = $byDay->get($day->toDateString(), collect()); @endphp
                        <div wire:key="day-{{ $day->toDateString() }}" @class(['group min-h-28 border-r border-b border-slate-100 p-1.5', 'bg-slate-50/70 text-slate-400' => ! $day->isSameMonth($monthStart)])>
                            <div class="flex items-center justify-between">
                                <span @class(['flex size-6 items-center justify-center rounded-full text-xs', 'bg-indigo-600 font-semibold text-white' => $day->isToday()])>{{ $day->day }}</span>
                                @can('create', \App\Models\Meeting::class)
                                    @if (! $day->isPast() || $day->isToday())
                                        <button wire:click="create('{{ $day->toDateString() }}')" class="hidden rounded px-1 text-xs text-slate-400 group-hover:block hover:bg-slate-100 hover:text-indigo-600" title="Schedule">+</button>
                                    @endif
                                @endcan
                            </div>
                            <div class="mt-1 space-y-1">
                                @foreach ($items as $m)
                                    <a href="{{ route('meetings.show', $m) }}" wire:navigate
                                       @class(['block truncate rounded px-1.5 py-0.5 text-xs', 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' => $m->status === 'scheduled', 'bg-emerald-50 text-emerald-700' => $m->status === 'completed', 'bg-slate-100 text-slate-400 line-through' => $m->status === 'cancelled'])>
                                        {{ $m->starts_at->format('H:i') }} {{ $m->title }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <x-card>
            <div class="divide-y divide-slate-100">
                @forelse ($meetings as $meeting)
                    @php $canDelete = $user->can('delete', $meeting); @endphp
                    <div wire:key="m-{{ $meeting->id }}" class="group relative">
                        <x-meeting-row :meeting="$meeting" :user="$user" :class="$canDelete ? 'pr-14' : ''" />
                        @if ($canDelete)
                            <button wire:click="deleteMeeting({{ $meeting->id }})"
                                    wire:confirm="Delete “{{ $meeting->title }}” ({{ $meeting->starts_at->format('d M, h:i A') }}) with its notes and attendance?{{ $meeting->isUpcoming() ? '\\nParticipants will be told it is cancelled.' : '' }}"
                                    class="absolute top-1/2 right-4 -translate-y-1/2 rounded p-1.5 text-slate-300 group-hover:text-slate-400 hover:bg-red-50 hover:!text-red-600" aria-label="Delete {{ $meeting->title }}"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </div>
                @empty
                    <x-empty>{{ $view === 'history' ? 'No past meetings yet.' : 'No upcoming meetings.' }}</x-empty>
                @endforelse
            </div>
        </x-card>
    @endif

    @can('create', \App\Models\Meeting::class)
        <x-modal wire:model="showForm" title="Schedule meeting">
            @include('livewire.meetings._form')
        </x-modal>
    @endcan
</div>
