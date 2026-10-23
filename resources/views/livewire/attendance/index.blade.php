@php
    $tabs = $user->tracksAttendance() ? ['mine' => 'My attendance'] : [];
    if ($user->isManager()) { $tabs += ['today' => 'Today', 'month' => 'Monthly sheet']; }
    $tabs['holidays'] = 'Holidays';
    $saturdayRule = collect((array) \App\Support\Settings::get('work.saturday_offs'))->map(fn ($n) => \App\Services\WorkCalendar::ordinal((int) $n))->join(', ', ' & ');
@endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex flex-wrap rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-indigo-600 text-white' => $tab === $key, 'text-slate-600 hover:bg-slate-50' => $tab !== $key])>{{ $label }}</button>
            @endforeach
        </div>
        <a href="{{ route('leave.index') }}" wire:navigate class="btn-secondary">🌴 {{ $user->tracksAttendance() ? 'Apply for leave' : 'Manage leave' }}</a>
    </div>
    <p class="text-xs text-slate-500">People are marked present automatically the first time they use the system each day (super admins are not tracked). Days off: all {{ collect(\App\Support\Settings::workingDays())->count() < 7 ? 'Sundays' : '' }}{{ $saturdayRule ? ', the '.$saturdayRule.' Saturday of every month' : '' }} and holidays. Late = signed in more than {{ \App\Support\Settings::get('work.late_grace_minutes') }} min after your start time.</p>

    {{-- ───────── My attendance ───────── --}}
    @if ($tab === 'mine')
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 md:col-span-2">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Today</p>
                @if ($todayInfo['type'] !== 'working')
                    <p class="mt-1 text-lg font-semibold text-violet-700">🎉 {{ $todayInfo['label'] }}</p>
                    <p class="text-sm text-slate-500">Not a working day{{ $todayRecord ? ', but you signed in at '.$todayRecord->check_in_at?->setTimezone($user->timezone)->format('h:i A') : '' }}.</p>
                @elseif ($todayRecord)
                    <p class="mt-1 text-lg font-semibold {{ $todayRecord->is_late ? 'text-amber-700' : 'text-emerald-700' }}">{{ $todayRecord->is_late ? '⏰ Present, late by '.$todayRecord->minutes_late.' min' : '✅ Present' }}</p>
                    <p class="text-sm text-slate-500">First seen {{ $todayRecord->check_in_at?->setTimezone($user->timezone)->format('h:i A') }} · last active {{ $todayRecord->last_seen_at?->setTimezone($user->timezone)->format('h:i A') }}</p>
                @else
                    <p class="mt-1 text-lg font-semibold text-slate-700">Not signed in yet today</p>
                @endif
            </div>
            @foreach ([['Present', $mine['summary']['present'], 'text-emerald-700'], ['Absent', $mine['summary']['absent'], $mine['summary']['absent'] ? 'text-red-600' : 'text-slate-900']] as [$label, $value, $tone])
                <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ $label }} · {{ $monthStart->format('M') }}</p><p class="text-2xl font-semibold {{ $tone }}">{{ $value + 0 }}</p></div>
            @endforeach
        </div>

        <x-card>
            <x-slot:title>
                <span class="flex items-center gap-2">
                    <button wire:click="shiftMonth(-1)" class="rounded p-1 hover:bg-slate-100" aria-label="Previous month">‹</button>
                    {{ $monthStart->format('F Y') }}
                    <button wire:click="shiftMonth(1)" class="rounded p-1 hover:bg-slate-100" aria-label="Next month">›</button>
                </span>
            </x-slot:title>
            <x-slot:actions>
                <span class="text-sm text-slate-500">{{ $mine['summary']['rate'] }}% attendance · {{ $mine['summary']['late'] }} late · {{ $mine['summary']['leave'] + 0 }} leave</span>
            </x-slot:actions>
            <div class="p-4">
                <div class="grid grid-cols-7 gap-1.5 text-center">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d) <span class="py-1 text-xs font-medium text-slate-400">{{ $d }}</span> @endforeach
                    @for ($i = 1; $i < $monthStart->dayOfWeekIso; $i++) <span></span> @endfor
                    @foreach ($mine['days'] as $date => $s)
                        @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                        <div @class(['flex min-h-16 flex-col items-center justify-center gap-1 rounded-lg border p-1', 'border-indigo-400' => $d->isToday(), 'border-slate-100' => ! $d->isToday()]) title="{{ $d->format('D d M') }}: {{ $s['label'] }}">
                            <span class="text-xs text-slate-500">{{ $d->day }}</span>
                            <x-attendance-cell :tone="$s['tone']" :code="$s['code']" class="h-6 min-w-8 px-1 text-xs" />
                            <span class="hidden max-w-full truncate text-[10px] text-slate-400 sm:block">{{ in_array($s['tone'], ['holiday', 'leave', 'half']) ? $s['label'] : '' }}</span>
                        </div>
                    @endforeach
                </div>
                <x-attendance-legend class="mt-4" />
            </div>
        </x-card>
    @endif

    {{-- ───────── Today (managers) ───────── --}}
    @if ($tab === 'today')
        @php $counts = $register->groupBy(fn ($r) => $r['status']['tone'])->map->count(); @endphp
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            @foreach ([['Present', ($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['extra'] ?? 0), 'text-emerald-700'], ['Late', $counts['late'] ?? 0, 'text-amber-700'], ['On leave', ($counts['leave'] ?? 0) + ($counts['half'] ?? 0), 'text-sky-700'], ['Not in yet', $counts['pending'] ?? 0, 'text-slate-700'], ['Total people', $register->count(), 'text-slate-900']] as [$label, $value, $tone])
                <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-2xl font-semibold {{ $tone }}">{{ $value }}</p></div>
            @endforeach
        </div>
        <x-card>
            <ul class="divide-y divide-slate-100">
                @foreach ($register as $r)
                    <li wire:key="reg-{{ $r['user']->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <x-avatar :user="$r['user']" presence />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $r['user']->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $r['user']->job_title }} · <x-local-time :user="$r['user']" /></p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            @if ($r['record']?->check_in_at)
                                <p>In {{ $r['record']->check_in_at->setTimezone($r['user']->timezone)->format('h:i A') }} · last active {{ $r['record']->last_seen_at?->setTimezone($r['user']->timezone)->format('h:i A') }}</p>
                            @endif
                        </div>
                        <x-attendance-cell :tone="$r['status']['tone']" :code="$r['status']['code'] ?: '–'" class="h-6 min-w-8 px-1.5 text-xs" />
                        <span class="w-36 truncate text-sm text-slate-700">{{ $r['status']['label'] }}</span>
                        @if ($r['canCorrect'])
                            <button wire:click="openCorrection({{ $r['user']->id }}, '{{ $r['date'] }}')" class="rounded px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50">Correct</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- ───────── Monthly sheet (managers) ───────── --}}
    @if ($tab === 'month')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button wire:click="shiftMonth(-1)" class="btn-secondary px-2.5 py-1">‹</button>
                <span class="font-semibold text-slate-900">{{ $monthStart->format('F Y') }}</span>
                <button wire:click="shiftMonth(1)" class="btn-secondary px-2.5 py-1">›</button>
            </div>
            <a href="{{ route('attendance.export', ['month' => $monthStart->format('Y-m')]) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Download CSV</a>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2 text-left font-medium">Person</th>
                            @foreach (array_keys($sheet->first()['days'] ?? []) as $date)
                                @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                                <th @class(['px-0.5 py-2 text-center font-medium', 'text-indigo-700' => $d->isToday(), 'text-slate-300' => ! $calendar->isWorkingDay($d)])>{{ $d->day }}<br><span class="text-[9px]">{{ substr($d->format('D'), 0, 2) }}</span></th>
                            @endforeach
                            <th class="px-2 py-2 text-right font-medium">Present</th>
                            <th class="px-2 py-2 text-right font-medium">Late</th>
                            <th class="px-2 py-2 text-right font-medium">Leave</th>
                            <th class="px-2 py-2 text-right font-medium">Absent</th>
                            <th class="px-3 py-2 text-right font-medium">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sheet as $row)
                            @php $canCorrect = ! $row['user']->is($user) && $user->can('update', $row['user']); @endphp
                            <tr wire:key="sh-{{ $row['user']->id }}">
                                <td class="sticky left-0 z-10 bg-white px-3 py-1.5 whitespace-nowrap"><span class="text-sm font-medium text-slate-900">{{ $row['user']->name }}</span></td>
                                @foreach ($row['days'] as $date => $s)
                                    <td class="px-0.5 py-1.5 text-center">
                                        @if ($canCorrect && \Illuminate\Support\Carbon::parse($date)->lte(today()->addDay()))
                                            <button wire:click="openCorrection({{ $row['user']->id }}, '{{ $date }}')" class="rounded hover:ring-2 hover:ring-indigo-300" aria-label="Correct {{ $row['user']->name }} on {{ $date }}">
                                                <x-attendance-cell :tone="$s['tone']" :code="$s['code']" :title="\Illuminate\Support\Carbon::parse($date)->format('D d M').': '.$s['label']" class="size-6 text-[10px]" />
                                            </button>
                                        @else
                                            <x-attendance-cell :tone="$s['tone']" :code="$s['code']" :title="\Illuminate\Support\Carbon::parse($date)->format('D d M').': '.$s['label']" class="size-6 text-[10px]" />
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2 text-right font-semibold text-emerald-700 tabular-nums">{{ $row['summary']['present'] + 0 }}</td>
                                <td class="px-2 text-right tabular-nums text-amber-700">{{ $row['summary']['late'] }}</td>
                                <td class="px-2 text-right tabular-nums text-sky-700">{{ $row['summary']['leave'] + 0 }}</td>
                                <td class="px-2 text-right tabular-nums {{ $row['summary']['absent'] ? 'font-semibold text-red-600' : 'text-slate-400' }}">{{ $row['summary']['absent'] }}</td>
                                <td class="px-3 text-right font-semibold tabular-nums">{{ $row['summary']['rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <x-attendance-legend />
        <p class="text-xs text-slate-400">Click any cell to correct it. Rate = present ÷ (working days so far − leave days).</p>
    @endif

    {{-- ───────── Holidays ───────── --}}
    @if ($tab === 'holidays')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button wire:click="shiftYear(-1)" class="btn-secondary px-2.5 py-1">‹</button>
                <span class="font-semibold text-slate-900">{{ $holidayYear }} holidays</span>
                <button wire:click="shiftYear(1)" class="btn-secondary px-2.5 py-1">›</button>
            </div>
            @if ($user->isManager())
                <div class="flex gap-2">
                    <button wire:click="copyFixedHolidays" wire:confirm="Copy {{ $holidayYear }}'s fixed-date holidays (Republic Day, May Day, Christmas…) to {{ $holidayYear + 1 }}?" class="btn-secondary">Copy fixed dates to {{ $holidayYear + 1 }}</button>
                    <button wire:click="editHoliday" class="btn-primary"><x-icon name="plus" class="size-4" /> Add holiday</button>
                </div>
            @endif
        </div>
        @if ($holidays->where('needs_confirmation', true)->isNotEmpty())
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">⚠️ {{ $holidays->where('needs_confirmation', true)->count() }} festival dates follow the lunar calendar and are estimates. Check them against the official Kerala Government holiday list, then edit or click <strong>Confirm</strong>.</p>
        @endif
        <x-card>
            <ul class="divide-y divide-slate-100">
                @forelse ($holidays as $h)
                    <li wire:key="hd-{{ $h->id }}" @class(['flex flex-wrap items-center gap-3 px-4 py-3', 'opacity-60' => $h->date->isPast() && ! $h->date->isToday()])>
                        <div class="w-14 shrink-0 rounded-lg border border-slate-200 text-center">
                            <p class="rounded-t-lg bg-violet-600 text-[10px] font-semibold text-white uppercase">{{ $h->date->format('M') }}</p>
                            <p class="py-0.5 text-lg font-semibold text-slate-900">{{ $h->date->format('d') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900">{{ $h->name }}</p>
                            <p class="text-xs text-slate-500">{{ $h->date->format('l') }}{{ $calendar->isWeeklyOff($h->date) ? ' · already a weekly off' : '' }}</p>
                        </div>
                        <x-badge :color="$h->typeBadge()">{{ $h->typeLabel() }}</x-badge>
                        @if ($h->is_optional) <x-badge>Optional</x-badge> @endif
                        @if ($h->needs_confirmation)
                            <x-badge color="bg-amber-100 text-amber-800">Verify date</x-badge>
                            @if ($user->isManager()) <button wire:click="confirmHoliday({{ $h->id }})" class="rounded px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Confirm</button> @endif
                        @endif
                        @if ($user->isManager())
                            <button wire:click="editHoliday({{ $h->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit {{ $h->name }}"><x-icon name="pencil" class="size-4" /></button>
                            <button wire:click="deleteHoliday({{ $h->id }})" wire:confirm="Remove {{ $h->name }}?" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Remove {{ $h->name }}"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </li>
                @empty
                    <li><x-empty>No holidays added for {{ $holidayYear }} yet.</x-empty></li>
                @endforelse
            </ul>
        </x-card>
    @endif

    {{-- ───────── Dialogs ───────── --}}
    @if ($user->isManager())
        <x-modal wire:model="showCorrection" :title="'Correct attendance'.($corrUser ? ' · '.$corrUser->name : '')" max-width="max-w-md">
            <form wire:submit="saveCorrection" class="space-y-4">
                <p class="text-sm text-slate-600">{{ $corrDate ? \Illuminate\Support\Carbon::parse($corrDate)->format('l, d F Y') : '' }}</p>
                <x-field label="Mark as" error="corrStatus">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['auto' => 'Automatic (from sign-ins)', 'present' => 'Present', 'half_day' => 'Half day', 'absent' => 'Absent'] as $value => $label)
                            <label @class(['cursor-pointer rounded-lg border px-3 py-2 text-sm', 'border-indigo-500 bg-indigo-50 text-indigo-800' => $corrStatus === $value, 'border-slate-200' => $corrStatus !== $value])>
                                <input type="radio" wire:model.live="corrStatus" value="{{ $value }}" class="sr-only"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </x-field>
                <x-field label="Reason" for="corr-note" error="corrNote">
                    <input wire:model="corrNote" id="corr-note" class="input" placeholder="e.g. Worked offline at client site">
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="showHoliday" :title="$holidayId ? 'Edit holiday' : 'Add holiday'" max-width="max-w-md">
            <form wire:submit="saveHoliday" class="space-y-4">
                <x-field label="Date" for="h-date" error="hDate"><input wire:model="hDate" id="h-date" type="date" class="input"></x-field>
                <x-field label="Name" for="h-name" error="hName"><input wire:model="hName" id="h-name" class="input" placeholder="e.g. Thiruvonam"></x-field>
                <x-field label="Type" for="h-type" error="hType">
                    <select wire:model="hType" id="h-type" class="input">
                        @foreach (\App\Models\Holiday::TYPES as $value => [$label]) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <label class="flex items-start gap-2 text-sm text-slate-700"><input wire:model="hOptional" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600"> <span>Optional (restricted) holiday: not a day off unless someone applies for leave</span></label>
                <label class="flex items-start gap-2 text-sm text-slate-700"><input wire:model="hNeedsConfirmation" type="checkbox" class="mt-0.5 rounded border-slate-300 text-amber-600"> <span>Date still needs to be verified</span></label>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
