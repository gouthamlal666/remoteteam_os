@php
    $tabs = ['mine' => 'My daily report'];
    if ($user->isManager()) {
        $tabs += ['team' => 'Team daily', 'weekly' => 'Weekly report', 'performance' => 'Performance'];
    }
@endphp
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div class="inline-flex flex-wrap rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach ($tabs as $value => $label)
                <button wire:click="$set('tab', '{{ $value }}')" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-indigo-600 text-white' => $tab === $value, 'text-slate-600 hover:bg-slate-50' => $tab !== $value])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($user->isManager() && $tab !== 'mine')
            <select wire:model.live="department" class="input w-auto">
                <option value="">All departments</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- ─────────────── My daily report ─────────────── --}}
    @if ($tab === 'mine')
        <div class="grid gap-6 lg:grid-cols-5">
            <x-card title="Daily work report" class="lg:col-span-3">
                <form wire:submit="submitReport" class="space-y-4 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Date" for="r-date" error="reportDate">
                            <input wire:model.live="reportDate" id="r-date" type="date" max="{{ today()->toDateString() }}" min="{{ today()->subDays(14)->toDateString() }}" class="input">
                        </x-field>
                        <x-field label="Hours worked" for="r-hours" error="hoursWorked">
                            <input wire:model="hoursWorked" id="r-hours" type="number" step="0.5" min="0" max="24" class="input" placeholder="7.5">
                        </x-field>
                    </div>
                    <x-field label="What did you complete?" for="r-done" error="completed">
                        <textarea wire:model="completed" id="r-done" rows="4" class="input" placeholder="- Finished login API&#10;- Reviewed Priya's PR"></textarea>
                    </x-field>
                    <x-field label="What are you working on?" for="r-wip" error="inProgress">
                        <textarea wire:model="inProgress" id="r-wip" rows="3" class="input"></textarea>
                    </x-field>
                    <x-field label="Any blockers?" for="r-block" error="blockers">
                        <textarea wire:model="blockers" id="r-block" rows="2" class="input" placeholder="Leave empty if none"></textarea>
                    </x-field>
                    <div class="flex justify-end"><button type="submit" class="btn-primary">Submit report</button></div>
                </form>
            </x-card>

            <x-card title="My recent reports" class="lg:col-span-2">
                <ul class="divide-y divide-slate-100">
                    @forelse ($myReports as $r)
                        <li wire:key="my-{{ $r->id }}" class="group relative">
                            <button wire:click="deleteReport({{ $r->id }})" wire:confirm="Delete your report for {{ $r->report_date->format('d M') }}?" class="absolute top-3 right-12 z-10 hidden text-slate-400 group-hover:block hover:text-red-600" aria-label="Delete report"><x-icon name="trash" class="size-4" /></button>
                            <button wire:click="editReport('{{ $r->report_date->toDateString() }}')" @class(['block w-full px-4 py-3 text-left hover:bg-slate-50', 'bg-indigo-50/60' => $r->report_date->toDateString() === $reportDate])>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-900">{{ $r->report_date->isToday() ? 'Today' : $r->report_date->format('D, d M') }}</span>
                                    <span class="text-xs text-slate-500">{{ rtrim(rtrim($r->hours_worked, '0'), '.') }} h</span>
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $r->completed }}</p>
                                @if ($r->blockers) <x-badge color="mt-1 bg-red-50 text-red-600">Blocker</x-badge> @endif
                            </button>
                        </li>
                    @empty
                        <li><x-empty>No reports yet — submit your first one.</x-empty></li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    @endif

    {{-- ─────────────── Team daily ─────────────── --}}
    @if ($tab === 'team')
        @php $submitted = $teamReports->count(); @endphp
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live="teamDate" type="date" max="{{ today()->toDateString() }}" class="input w-auto">
            <p class="text-sm text-slate-600"><span class="font-semibold text-slate-900">{{ $submitted }}/{{ $team->count() }}</span> submitted for {{ $teamDay->format('l, d M Y') }}</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($team as $person)
                @php $r = $teamReports->get($person->id); @endphp
                <div wire:key="td-{{ $person->id }}" @class(['rounded-xl border bg-white p-4', 'border-slate-200' => $r, 'border-dashed border-slate-300' => ! $r])>
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$person" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('users.show', $person) }}" wire:navigate class="text-sm font-medium text-slate-900 hover:text-indigo-700">{{ $person->name }}</a>
                            <p class="text-xs text-slate-500">{{ $person->job_title }}{{ $person->department ? ' · '.$person->department->name : '' }}</p>
                        </div>
                        @if ($r)
                            @if ($user->isSuperAdmin())
                                <button wire:click="deleteReport({{ $r->id }})" wire:confirm="Delete {{ $person->name }}'s report for {{ $teamDay->format('d M') }}?" class="text-slate-400 hover:text-red-600" aria-label="Delete report"><x-icon name="trash" class="size-4" /></button>
                            @endif
                            <x-badge color="bg-emerald-100 text-emerald-700">{{ rtrim(rtrim($r->hours_worked, '0'), '.') }} h</x-badge>
                        @else
                            <x-badge>Not submitted</x-badge>
                        @endif
                    </div>
                    @if ($r)
                        <dl class="mt-3 space-y-2 text-sm">
                            <div><dt class="text-xs font-medium text-slate-400 uppercase">Completed</dt><dd class="whitespace-pre-line text-slate-700">{{ $r->completed }}</dd></div>
                            @if ($r->in_progress)<div><dt class="text-xs font-medium text-slate-400 uppercase">Working on</dt><dd class="whitespace-pre-line text-slate-700">{{ $r->in_progress }}</dd></div>@endif
                            @if ($r->blockers)<div class="rounded-lg bg-red-50 p-2"><dt class="text-xs font-medium text-red-600 uppercase">Blockers</dt><dd class="whitespace-pre-line text-red-800">{{ $r->blockers }}</dd></div>@endif
                        </dl>
                    @endif
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white"><x-empty>No team members to show.</x-empty></div>
            @endforelse
        </div>
    @endif

    {{-- ─────────────── Weekly ─────────────── --}}
    @if ($tab === 'weekly')
        @php
            $weekEnd = $weekStart->copy()->addDays(6);
            $perWeek = app(\App\Services\WorkCalendar::class)->countWorkingDays($weekStart, $weekStart->copy()->addDays(6)) ?: 1;
            $totals = ['hours' => $rows->sum('hours'), 'done' => $rows->sum(fn ($r) => $r['completed']->count()), 'onTime' => $rows->sum('completed_on_time'), 'overdue' => $rows->sum(fn ($r) => $r['overdue']->count()), 'reports' => $rows->sum('days_reported')];
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 print:hidden">
                <button wire:click="shiftWeek(-1)" class="btn-secondary px-2.5 py-1">‹</button>
                <span class="text-sm font-medium text-slate-900">Week of {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</span>
                <button wire:click="shiftWeek(1)" class="btn-secondary px-2.5 py-1" @disabled($weekEnd->isFuture())>›</button>
            </div>
            <h2 class="hidden text-lg font-semibold print:block">Weekly Team Report · {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</h2>
            <button onclick="window.print()" class="btn-secondary print:hidden"><x-icon name="download" class="size-4" /> Print / PDF</button>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <x-stat label="Tasks completed" :value="$totals['done']" tone="text-emerald-600" />
            <x-stat label="On time" :value="$totals['done'] ? round($totals['onTime'] / $totals['done'] * 100).'%' : '—'" />
            <x-stat label="Currently overdue" :value="$totals['overdue']" tone="text-red-600" />
            <x-stat label="Hours logged" :value="rtrim(rtrim(number_format($totals['hours'], 1), '0'), '.')" />
            <x-stat label="Daily reports" :value="$totals['reports'].' / '.($rows->count() * $perWeek)" :hint="'expected: '.$perWeek.' per person'" />
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Person</th>
                            <th class="px-4 py-3">Reports</th>
                            <th class="px-4 py-3">Hours <span class="font-normal normal-case">(reported / tracked)</span></th>
                            <th class="px-4 py-3">Completed this week</th>
                            <th class="px-4 py-3">Overdue</th>
                            <th class="px-4 py-3">Blockers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 align-top">
                        @forelse ($rows as $row)
                            <tr wire:key="w-{{ $row['user']->id }}">
                                <td class="px-4 py-3"><p class="font-medium text-slate-900">{{ $row['user']->name }}</p><p class="text-xs text-slate-500">{{ $row['user']->job_title }}</p></td>
                                <td class="px-4 py-3 tabular-nums"><span @class(['text-amber-600' => $row['days_reported'] < $perWeek])>{{ $row['days_reported'] }}/{{ $perWeek }}</span></td>
                                <td class="px-4 py-3 tabular-nums">{{ rtrim(rtrim(number_format($row['hours'], 1), '0'), '.') ?: 0 }} <span class="text-slate-400">/ {{ \App\Models\TimeEntry::format($row['tracked_minutes']) }}</span></td>
                                <td class="px-4 py-3">
                                    @forelse ($row['completed'] as $t)
                                        <a href="{{ route('tasks.show', $t->id) }}" wire:navigate class="block truncate text-slate-700 hover:text-indigo-700">✓ {{ $t->title }} @unless ($t->completedOnTime()) <span class="text-xs text-red-600">(late)</span> @endunless</a>
                                    @empty <span class="text-slate-400">—</span> @endforelse
                                </td>
                                <td class="px-4 py-3">
                                    @forelse ($row['overdue'] as $t)
                                        <a href="{{ route('tasks.show', $t->id) }}" wire:navigate class="block truncate text-red-600 hover:underline">{{ $t->title }}</a>
                                    @empty <span class="text-slate-400">—</span> @endforelse
                                </td>
                                <td class="max-w-xs px-4 py-3">
                                    @forelse ($row['blockers'] as $r)
                                        <p class="text-xs text-slate-700"><span class="text-slate-400">{{ $r->report_date->format('D') }}:</span> {{ \Illuminate\Support\Str::limit($r->blockers, 120) }}</p>
                                    @empty <span class="text-slate-400">—</span> @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty>No team members.</x-empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ─────────────── Performance ─────────────── --}}
    @if ($tab === 'performance')
        <x-card title="Member performance">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3">Person</th>
                            <th class="w-48 px-4 py-3">Completion</th>
                            <th class="px-4 py-3 text-right">Assigned</th>
                            <th class="px-4 py-3 text-right">Done</th>
                            <th class="px-4 py-3 text-right">Pending</th>
                            <th class="px-4 py-3 text-right">Overdue</th>
                            <th class="px-4 py-3 text-right">On time</th>
                            <th class="px-4 py-3 text-right">Revisions</th>
                            <th class="px-4 py-3 text-right">Meetings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($people as $p)
                            <tr wire:key="perf-{{ $p['user']->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3"><a href="{{ route('users.show', $p['user']) }}" wire:navigate class="flex items-center gap-2"><x-avatar :user="$p['user']" size="size-7" /><span class="font-medium text-slate-900">{{ $p['user']->name }}</span></a></td>
                                <td class="px-4 py-3"><div class="flex items-center gap-2"><x-progress :value="$p['completion_rate']" /><span class="w-9 text-right text-xs tabular-nums">{{ $p['completion_rate'] }}%</span></div></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['assigned'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600">{{ $p['completed'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-amber-600">{{ $p['pending'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $p['overdue'] ? 'text-red-600 font-medium' : '' }}">{{ $p['overdue'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['completed'] ? $p['on_time_rate'].'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['revisions'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $p['meeting_attendance'] !== null ? $p['meeting_attendance'].'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-empty>No team members.</x-empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">All numbers come from assigned tasks and meeting attendance. On time = completed on or before the deadline. Meetings = share of completed meetings attended.</p>
        </x-card>

        <x-card title="Monthly report (tasks you can see)">
            @php $max = max(1, $trend->max(fn ($m) => max($m['created'], $m['done']))); @endphp
            <div class="grid gap-6 p-4 lg:grid-cols-2">
                <table class="text-sm">
                    <thead class="text-left text-xs text-slate-500 uppercase"><tr><th class="py-2 pr-4"></th>@foreach ($trend as $m)<th class="px-2 py-2 text-right">{{ \Illuminate\Support\Str::before($m['label'], ' ') }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100 tabular-nums">
                        <tr><td class="py-2 pr-4 text-slate-500">Tasks</td>@foreach ($trend as $m)<td class="px-2 py-2 text-right">{{ $m['created'] }}</td>@endforeach</tr>
                        <tr><td class="py-2 pr-4 text-slate-500">Done</td>@foreach ($trend as $m)<td class="px-2 py-2 text-right text-emerald-600">{{ $m['done'] }}</td>@endforeach</tr>
                        <tr><td class="py-2 pr-4 text-slate-500">Late</td>@foreach ($trend as $m)<td class="px-2 py-2 text-right text-red-600">{{ $m['late'] }}</td>@endforeach</tr>
                    </tbody>
                </table>
                <div>
                    <div class="flex h-40 items-end gap-3" role="img" aria-label="Tasks created vs completed per month">
                        @foreach ($trend as $m)
                            <div class="flex flex-1 flex-col items-center gap-1">
                                <div class="flex h-32 w-full items-end justify-center gap-1">
                                    <div class="w-3 rounded-t bg-slate-300" style="height: {{ $m['created'] / $max * 100 }}%" title="{{ $m['created'] }} created"></div>
                                    <div class="w-3 rounded-t bg-emerald-500" style="height: {{ $m['done'] / $max * 100 }}%" title="{{ $m['done'] }} done"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ \Illuminate\Support\Str::before($m['label'], ' ') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-4 text-xs text-slate-500"><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-slate-300"></span>Created</span><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-emerald-500"></span>Completed</span></div>
                </div>
            </div>
        </x-card>
    @endif
</div>
