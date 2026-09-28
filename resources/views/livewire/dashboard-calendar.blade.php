@php
    $appTz = config('app.timezone');
    $myTz = auth()->user()->timezone ?: $appTz;
    $done = \App\Enums\TaskStatus::Completed;
    $taskColor = fn ($t) => $t->status === $done
        ? 'bg-emerald-50 text-emerald-700 line-through decoration-emerald-400'
        : ($t->isOverdue() ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800');
@endphp
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white" wire:poll.60s.visible>
    <div class="grid lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- ───────── Month ───────── --}}
        <div class="min-w-0 lg:border-r lg:border-slate-100">
            <header class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-4 py-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ $monthStart->format('F Y') }}</h2>
                <div class="flex items-center rounded-lg border border-slate-200">
                    <button wire:click="shiftMonth(-1)" class="rounded-l-lg p-1.5 text-slate-500 hover:bg-slate-50" aria-label="Previous month">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" /></svg>
                    </button>
                    <button wire:click="goToday" class="border-x border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Today</button>
                    <button wire:click="shiftMonth(1)" class="rounded-r-lg p-1.5 text-slate-500 hover:bg-slate-50" aria-label="Next month">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    </button>
                </div>
                <div class="ml-auto flex items-center gap-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-indigo-500"></span>Meeting</span>
                    <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-amber-400"></span>Task due</span>
                    <span class="flex items-center gap-1"><span class="size-2 rounded-sm bg-red-500"></span>Overdue</span>
                    <span class="hidden items-center gap-1 sm:flex"><span class="size-2 rounded-sm bg-emerald-400"></span>Done</span>
                </div>
            </header>

            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-xs font-medium text-slate-500">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                    <span class="py-2">{{ $d }}</span>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php
                        $key = $day->toDateString();
                        $meetings = $meetingsByDay->get($key, collect());
                        $tasks = $tasksByDay->get($key, collect());
                        $items = $meetings->map(fn ($m) => ['type' => 'meeting', 'model' => $m])
                            ->concat($tasks->map(fn ($t) => ['type' => 'task', 'model' => $t]));
                        $isSelected = $key === $selectedDay->toDateString();
                        $inMonth = $day->isSameMonth($monthStart);
                    @endphp
                    <div wire:key="cal-{{ $key }}" wire:click="select('{{ $key }}')" role="button" tabindex="0"
                         wire:keydown.enter="select('{{ $key }}')"
                         @class([
                             'group min-h-16 cursor-pointer border-r border-b border-slate-100 p-1.5 text-left transition sm:min-h-28 [&:nth-child(7n)]:border-r-0',
                             'bg-indigo-50/70' => $isSelected,
                             'bg-slate-50/60' => ! $inMonth && ! $isSelected,
                             'hover:bg-slate-50' => ! $isSelected,
                         ])>
                        <div class="flex items-center justify-between">
                            <span @class([
                                'flex size-7 items-center justify-center rounded-full text-sm',
                                'bg-indigo-600 font-semibold text-white' => $day->isToday(),
                                'font-semibold text-indigo-700 ring-1 ring-indigo-300' => $isSelected && ! $day->isToday(),
                                'text-slate-800' => $inMonth && ! $day->isToday() && ! $isSelected,
                                'text-slate-300' => ! $inMonth && ! $day->isToday() && ! $isSelected,
                            ])>{{ $day->day }}</span>
                            @if ($items->count() > 0)
                                <span class="text-[10px] text-slate-400 sm:hidden">{{ $items->count() }}</span>
                            @endif
                        </div>

                        {{-- Phones: dots --}}
                        @if ($items->isNotEmpty())
                            <div class="mt-1 flex justify-center gap-0.5 sm:hidden">
                                @if ($meetings->isNotEmpty()) <span class="size-1.5 rounded-full bg-indigo-500"></span> @endif
                                @if ($tasks->isNotEmpty()) <span class="size-1.5 rounded-full {{ $tasks->contains(fn ($t) => $t->isOverdue()) ? 'bg-red-500' : 'bg-amber-400' }}"></span> @endif
                            </div>
                        @endif

                        {{-- Larger screens: event chips --}}
                        <div class="mt-1 hidden space-y-0.5 sm:block">
                            @foreach ($items->take(3) as $item)
                                @if ($item['type'] === 'meeting')
                                    @php $m = $item['model']; @endphp
                                    <a href="{{ route('meetings.show', $m) }}" wire:navigate x-on:click.stop
                                       @class([
                                           'flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[11px] font-medium',
                                           'bg-slate-100 text-slate-400 line-through' => $m->status === 'cancelled',
                                           'bg-red-500 text-white' => $m->isLive(),
                                           'bg-indigo-500 text-white hover:bg-indigo-600' => $m->status !== 'cancelled' && ! $m->isLive(),
                                       ])
                                       title="{{ $m->starts_at->format('h:i A') }} · {{ $m->title }}">
                                        @if ($m->isLive()) <span class="size-1.5 shrink-0 animate-pulse rounded-full bg-white"></span> @endif
                                        <span class="shrink-0 opacity-80">{{ $m->starts_at->format('g:i') }}</span>
                                        <span class="truncate">{{ $m->title }}</span>
                                    </a>
                                @else
                                    @php $t = $item['model']; @endphp
                                    <a href="{{ route('tasks.show', $t) }}" wire:navigate x-on:click.stop
                                       class="block truncate rounded px-1.5 py-0.5 text-[11px] font-medium {{ $taskColor($t) }} hover:brightness-95"
                                       title="Due: {{ $t->title }} ({{ $t->project->name }})">{{ $t->title }}</a>
                                @endif
                            @endforeach
                            @if ($items->count() > 3)
                                <span class="block px-1.5 text-[11px] font-medium text-slate-500">+{{ $items->count() - 3 }} more</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ───────── Clock + agenda ───────── --}}
        <aside class="flex flex-col border-t border-slate-100 lg:border-t-0">
            <div class="bg-gradient-to-br from-indigo-600 to-violet-600 px-5 py-5 text-white"
                 x-data="{ now: new Date(), fmt(tz, opts) { return this.now.toLocaleString('en-GB', { timeZone: tz, ...opts }) } }"
                 x-init="setInterval(() => now = new Date(), 1000)">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-3xl font-semibold tabular-nums" x-text="fmt(@js($appTz), { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).toUpperCase()">{{ now()->format('h:i:s A') }}</p>
                        <p class="text-sm text-indigo-100" x-text="fmt(@js($appTz), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })">{{ now()->format('l, j F Y') }}</p>
                    </div>
                    <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium">
                        <span class="relative flex size-1.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-300 opacity-75"></span><span class="relative inline-flex size-1.5 rounded-full bg-emerald-300"></span></span>
                        Live
                    </span>
                </div>
                @if ($myTz !== $appTz)
                    <p class="mt-3 border-t border-white/15 pt-2 text-xs text-indigo-100">
                        Your local time: <span class="font-semibold text-white tabular-nums" x-text="fmt(@js($myTz), { hour: '2-digit', minute: '2-digit', hour12: true }).toUpperCase()"></span>
                        <span class="opacity-75">({{ str_replace('_', ' ', $myTz) }})</span>
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between px-5 pt-4 pb-2">
                <h3 class="font-semibold text-slate-900">
                    {{ $selectedDay->isToday() ? 'Today' : ($selectedDay->isTomorrow() ? 'Tomorrow' : ($selectedDay->isYesterday() ? 'Yesterday' : $selectedDay->format('l, j M'))) }}
                </h3>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $dayMeetings->count() + $dayTasks->count() }} {{ str('item')->plural($dayMeetings->count() + $dayTasks->count()) }}</span>
            </div>

            <ul class="max-h-[26rem] flex-1 space-y-1 overflow-y-auto px-3 pb-3">
                @foreach ($dayMeetings as $m)
                    @php [$label, $badge] = $m->displayStatus(); @endphp
                    <li wire:key="ag-m-{{ $m->id }}">
                        <a href="{{ route('meetings.show', $m) }}" wire:navigate @class(['flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50', 'bg-red-50/70' => $m->isLive()])>
                            <span class="w-16 shrink-0 text-xs font-medium tabular-nums text-slate-500">{{ $m->starts_at->format('h:i A') }}</span>
                            <span class="h-9 w-1 shrink-0 rounded-full {{ $m->status === 'cancelled' ? 'bg-slate-300' : 'bg-indigo-500' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm font-medium', 'text-slate-400 line-through' => $m->status === 'cancelled', 'text-slate-900' => $m->status !== 'cancelled'])>{{ $m->title }}</span>
                                <span class="block text-xs text-slate-500">{{ $m->duration_minutes }} min meeting</span>
                            </span>
                            @if ($m->isLive())
                                <x-badge color="bg-red-100 text-red-700">● Live now</x-badge>
                            @elseif ($m->isUpcoming() && $m->starts_at->diffInMinutes(now(), true) <= 60)
                                <x-badge color="bg-amber-100 text-amber-800">in {{ (int) ceil(now()->diffInSeconds($m->starts_at) / 60) }} min</x-badge>
                            @else
                                <x-badge :color="$badge">{{ $label }}</x-badge>
                            @endif
                        </a>
                    </li>
                @endforeach
                @foreach ($dayTasks as $t)
                    <li wire:key="ag-t-{{ $t->id }}">
                        <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                            <span class="w-16 shrink-0 text-xs font-medium text-slate-500">Due</span>
                            <span class="h-9 w-1 shrink-0 rounded-full {{ $t->status === $done ? 'bg-emerald-400' : ($t->isOverdue() ? 'bg-red-500' : 'bg-amber-400') }}"></span>
                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-sm font-medium', 'text-slate-400 line-through' => $t->status === $done, 'text-slate-900' => $t->status !== $done])>{{ $t->title }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $t->project->name }}</span>
                            </span>
                            <x-task-status :task="$t" />
                        </a>
                    </li>
                @endforeach
                @if ($dayMeetings->isEmpty() && $dayTasks->isEmpty())
                    <li class="flex flex-col items-center gap-1 px-2 py-10 text-center">
                        <x-icon name="calendar" class="size-8 text-slate-300" />
                        <span class="text-sm text-slate-400">Nothing scheduled</span>
                    </li>
                @endif
            </ul>
            <a href="{{ route('meetings.index', ['view' => 'calendar', 'month' => $monthStart->format('Y-m')]) }}" wire:navigate class="mt-auto block border-t border-slate-100 px-4 py-3 text-center text-xs font-medium text-indigo-600 hover:bg-slate-50">Open meetings calendar →</a>
        </aside>
    </div>
</section>
