@php
    $tabs = $user->tracksAttendance() ? ['mine' => 'My leave'] : [];
    $isOwner = $user->isSuperAdmin();
    if ($user->isManager()) { $tabs += ['approvals' => 'Approvals', 'all' => 'All requests', 'types' => 'Leave types']; }
@endphp
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex flex-wrap rounded-lg border border-slate-200 bg-white p-1 text-sm">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')" @class(['flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium', 'bg-indigo-600 text-white' => $tab === $key, 'text-slate-600 hover:bg-slate-50' => $tab !== $key])>
                    {{ $label }}
                    @if ($key === 'approvals' && $pending->isNotEmpty())
                        <span @class(['rounded-full px-1.5 text-[10px] font-semibold', 'bg-white text-indigo-700' => $tab === $key, 'bg-amber-500 text-white' => $tab !== $key])>{{ $pending->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <div class="flex gap-2">
            <a href="{{ route('attendance.index', ['tab' => 'holidays']) }}" wire:navigate class="btn-secondary"><x-icon name="calendar" class="size-4" /> Holidays</a>
            @if ($isOwner)
                <button wire:click="openEntry" class="btn-primary"><x-icon name="plus" class="size-4" /> Add leave</button>
            @else
                <button wire:click="openApply" class="btn-primary"><x-icon name="plus" class="size-4" /> Apply for leave</button>
            @endif
        </div>
    </div>

    {{-- Balances --}}
    <div @class(['grid grid-cols-2 gap-3 lg:grid-cols-4', 'hidden' => $balances->isEmpty()])>
        @foreach ($balances as $b)
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full" style="background: {{ $b['type']->hex() }}"></span>
                    <p class="truncate text-sm font-medium text-slate-700">{{ $b['type']->name }}</p>
                </div>
                @if ($b['type']->isUnlimited())
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $b['used'] + 0 }} <span class="text-sm font-normal text-slate-500">taken in {{ $year }}</span></p>
                    <p class="text-xs text-slate-400">No limit{{ $b['pending'] ? ' · '.($b['pending'] + 0).' pending' : '' }}</p>
                @else
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $b['remaining'] + 0 }} <span class="text-sm font-normal text-slate-500">of {{ $b['allowance'] }} left</span></p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" style="width: {{ $b['allowance'] ? min(100, ($b['used'] + $b['pending']) / $b['allowance'] * 100) : 0 }}%; background: {{ $b['type']->hex() }}"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $b['used'] + 0 }} taken{{ $b['pending'] ? ' · '.($b['pending'] + 0).' pending' : '' }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- ───────── My leave ───────── --}}
            @if ($tab === 'mine')
                <x-card title="My requests">
                    <ul class="divide-y divide-slate-100">
                        @forelse ($myRequests as $r)
                            <li wire:key="my-{{ $r->id }}" class="flex flex-wrap items-start gap-3 px-4 py-3">
                                <span class="mt-1.5 size-2.5 shrink-0 rounded-full" style="background: {{ $r->type->hex() }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900">{{ $r->type->name }} · {{ $r->days + 0 }} day{{ $r->days == 1 ? '' : 's' }}</p>
                                    <p class="text-xs text-slate-500">{{ $r->periodLabel() }}</p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $r->reason }}</p>
                                    @if ($r->reviewed_by)
                                        <p class="mt-1 text-xs text-slate-500">{{ $r->statusLabel() }} by {{ $r->reviewer->name }} {{ $r->reviewed_at?->diffForHumans() }}{{ $r->review_note ? ': “'.$r->review_note.'”' : '' }}</p>
                                    @endif
                                </div>
                                <x-badge :color="$r->statusBadge()">{{ $r->statusLabel() }}</x-badge>
                                @if (app(\App\Services\LeaveService::class)->canCancel($user, $r))
                                    <button wire:click="cancel({{ $r->id }})" wire:confirm="Cancel this leave request?" class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">Cancel</button>
                                @endif
                            </li>
                        @empty
                            <li><x-empty>You haven't applied for any leave yet.</x-empty></li>
                        @endforelse
                    </ul>
                    @if ($myRequests->hasPages()) <div class="border-t border-slate-100 px-4 py-3">{{ $myRequests->links() }}</div> @endif
                </x-card>
            @endif

            {{-- ───────── Approvals ───────── --}}
            @if ($tab === 'approvals')
                <x-card title="Waiting for your approval">
                    <ul class="divide-y divide-slate-100">
                        @forelse ($pending as $r)
                            @php $bal = $pendingBalances[$r->id]; @endphp
                            <li wire:key="pd-{{ $r->id }}" class="flex flex-wrap items-start gap-3 px-4 py-4">
                                <x-avatar :user="$r->user" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">{{ $r->user->name }} <span class="font-normal text-slate-500">· {{ $r->type->name }}, {{ $r->days + 0 }} day{{ $r->days == 1 ? '' : 's' }}</span></p>
                                    <p class="text-xs text-slate-500">{{ $r->periodLabel() }} · applied {{ $r->created_at->diffForHumans() }}</p>
                                    <p class="mt-1 text-sm text-slate-700">{{ $r->reason }}</p>
                                    @unless ($r->type->isUnlimited())
                                        <p class="mt-1 text-xs text-slate-400">Balance: {{ $bal['remaining'] + 0 }} of {{ $bal['allowance'] + 0 }} before this request</p>
                                    @endunless
                                </div>
                                <div class="flex gap-2">
                                    @if ($isOwner) <button wire:click="openEntry({{ $r->id }})" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit"><x-icon name="pencil" class="size-4" /></button> @endif
                                    <button wire:click="openReview({{ $r->id }}, 'reject')" class="btn-secondary px-3 py-1.5 text-xs">Reject</button>
                                    <button wire:click="openReview({{ $r->id }}, 'approve')" class="btn-success px-3 py-1.5 text-xs">Approve</button>
                                </div>
                            </li>
                        @empty
                            <li><x-empty>🎉 Nothing waiting for approval.</x-empty></li>
                        @endforelse
                    </ul>
                </x-card>
            @endif

            {{-- ───────── All requests ───────── --}}
            @if ($tab === 'all')
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="status" class="input w-auto">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\LeaveRequest::STATUSES as $value => [$label]) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                    </select>
                    <select wire:model.live="person" class="input w-auto">
                        <option value="">Everyone</option>
                        @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                    </select>
                </div>
                <x-card>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($requests as $r)
                            <li wire:key="all-{{ $r->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                                <x-avatar :user="$r->user" size="size-7" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $r->user->name }} <span class="font-normal text-slate-500">· {{ $r->type->name }}, {{ $r->days + 0 }}d</span></p>
                                    <p class="truncate text-xs text-slate-500">{{ $r->periodLabel() }}{{ $r->reviewed_by ? ' · by '.$r->reviewer->name : '' }}</p>
                                </div>
                                <x-badge :color="$r->statusBadge()">{{ $r->statusLabel() }}</x-badge>
                                @if ($r->status === 'pending' && $user->can('review', $r))
                                    <button wire:click="openReview({{ $r->id }}, 'approve')" class="rounded px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50">Review</button>
                                @endif
                                @if ($isOwner)
                                    <button wire:click="openEntry({{ $r->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit leave"><x-icon name="pencil" class="size-4" /></button>
                                    <button wire:click="deleteLeave({{ $r->id }})" wire:confirm="Delete {{ $r->user->name }}'s {{ $r->type->name }} ({{ $r->periodLabel() }}) permanently?" class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Delete leave"><x-icon name="trash" class="size-4" /></button>
                                @endif
                            </li>
                        @empty
                            <li><x-empty>No leave requests match.</x-empty></li>
                        @endforelse
                    </ul>
                    @if ($requests->hasPages()) <div class="border-t border-slate-100 px-4 py-3">{{ $requests->links() }}</div> @endif
                </x-card>
            @endif

            {{-- ───────── Leave types ───────── --}}
            @if ($tab === 'types')
                <x-card title="Leave types">
                    <x-slot:actions><button wire:click="editType" class="btn-secondary px-3 py-1.5 text-xs"><x-icon name="plus" class="size-4" /> Add type</button></x-slot:actions>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($allTypes as $t)
                            <li wire:key="lt-{{ $t->id }}" @class(['flex items-center gap-3 px-4 py-3', 'opacity-50' => ! $t->is_active])>
                                <span class="size-3 rounded-full" style="background: {{ $t->hex() }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900">{{ $t->name }} <span class="text-xs text-slate-400">{{ $t->code }}</span></p>
                                    <p class="text-xs text-slate-500">{{ $t->isUnlimited() ? 'Unlimited' : $t->days_per_year.' days a year' }} · {{ $t->allow_half_day ? 'half days allowed' : 'full days only' }}{{ $t->is_active ? '' : ' · inactive' }}</p>
                                </div>
                                <button wire:click="editType({{ $t->id }})" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Edit {{ $t->name }}"><x-icon name="pencil" class="size-4" /></button>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
                <p class="text-xs text-slate-400">Allowances reset every calendar year. Days are counted on working days only, so Sundays, off-Saturdays and holidays inside a leave are never deducted.</p>
            @endif
        </div>

        {{-- Who's off --}}
        <x-card title="Who's off · next 2 weeks">
            <ul class="divide-y divide-slate-100">
                @forelse ($whosOff as $r)
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <x-avatar :user="$r->user" size="size-7" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $r->user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $r->periodLabel() }}</p>
                        </div>
                        <span class="size-2.5 rounded-full" style="background: {{ $r->type->hex() }}" title="{{ $r->type->name }}"></span>
                    </li>
                @empty
                    <li><x-empty>Everyone's in.</x-empty></li>
                @endforelse
            </ul>
        </x-card>
    </div>

    {{-- ───────── Dialogs ───────── --}}
    @php $formBalances = $isOwner ? $entryBalances : $balances; @endphp
    <x-modal wire:model="showApply" :title="$isOwner ? ($editingId ? 'Edit leave' : 'Add leave') : 'Apply for leave'" max-width="max-w-lg">
        <form wire:submit="{{ $isOwner ? 'saveEntry' : 'apply' }}" class="space-y-4">
            @if ($isOwner)
                <x-field label="Employee" for="lv-user" error="forUserId">
                    <select wire:model.live="forUserId" id="lv-user" class="input" @disabled($editingId)>
                        <option value="">Choose a person…</option>
                        @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}{{ $p->isAdmin() ? ' (Admin)' : '' }}</option> @endforeach
                    </select>
                </x-field>
            @endif
            <x-field label="Leave type" for="lv-type" error="leave_type_id">
                <select wire:model.live="leave_type_id" id="lv-type" class="input">
                    @if ($formBalances->isEmpty())
                        @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
                    @endif
                    @foreach ($formBalances as $b)
                        <option value="{{ $b['type']->id }}">{{ $b['type']->name }}{{ $b['type']->isUnlimited() ? '' : ' ('.($b['remaining'] + 0).' left)' }}</option>
                    @endforeach
                </select>
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="From" for="lv-start" error="start_date"><input wire:model.live="start_date" id="lv-start" type="date" class="input"></x-field>
                <x-field label="To" for="lv-end" error="end_date"><input wire:model.live="end_date" id="lv-end" type="date" min="{{ $start_date }}" class="input"></x-field>
            </div>
            @if ($start_date && $start_date === $end_date && $types->firstWhere('id', $leave_type_id)?->allow_half_day)
                <x-field label="Duration" error="half_day">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        @foreach (['' => 'Full day', 'first_half' => 'First half', 'second_half' => 'Second half'] as $value => $label)
                            <label @class(['cursor-pointer rounded-lg border px-3 py-2 text-center', 'border-indigo-500 bg-indigo-50 text-indigo-800' => $half_day === $value, 'border-slate-200' => $half_day !== $value])>
                                <input type="radio" wire:model.live="half_day" value="{{ $value }}" class="sr-only"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </x-field>
            @endif
            @if ($preview)
                <div class="rounded-lg bg-slate-50 p-3 text-sm">
                    <p class="font-medium text-slate-800">This uses <span class="text-indigo-700">{{ $preview['days'] + 0 }} day{{ $preview['days'] == 1 ? '' : 's' }}</span> of leave.</p>
                    @if ($preview['skipped']->isNotEmpty())
                        <p class="mt-1 text-xs text-slate-500">Not counted: {{ $preview['skipped']->take(6)->join(', ') }}{{ $preview['skipped']->count() > 6 ? ' and '.($preview['skipped']->count() - 6).' more' : '' }}</p>
                    @endif
                </div>
            @endif
            <x-field label="Reason" for="lv-reason" error="reason">
                <textarea wire:model="reason" id="lv-reason" rows="3" class="input" placeholder="e.g. Family function in Thrissur"></textarea>
            </x-field>
            @if ($isOwner)
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Status" for="lv-status" error="entryStatus">
                        <select wire:model="entryStatus" id="lv-status" class="input">
                            @foreach (\App\Models\LeaveRequest::STATUSES as $value => [$label]) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Note (optional)" for="lv-note" error="entryNote"><input wire:model="entryNote" id="lv-note" class="input"></x-field>
                </div>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ $isOwner ? 'Save' : 'Send for approval' }}</button>
            </div>
        </form>
    </x-modal>

    @if ($user->isManager())
        @if ($reviewing)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" wire:key="review-{{ $reviewing->id }}" x-data x-on:keydown.escape.window="$wire.set('reviewId', null)">
                <div class="w-full max-w-md rounded-2xl bg-white shadow-xl" x-on:click.outside="$wire.set('reviewId', null)">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="text-base font-semibold text-slate-900">{{ $reviewAction === 'reject' ? 'Reject' : 'Approve' }} {{ $reviewing->user->name }}'s leave</h3>
                        <p class="text-sm text-slate-500">{{ $reviewing->type->name }} · {{ $reviewing->periodLabel() }} · {{ $reviewing->days + 0 }} day(s)</p>
                    </div>
                    <form wire:submit="saveReview" class="space-y-4 p-5">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            @foreach (['approve' => '✓ Approve', 'reject' => '✕ Reject'] as $value => $label)
                                <label @class(['cursor-pointer rounded-lg border px-3 py-2 text-center font-medium', 'border-emerald-500 bg-emerald-50 text-emerald-800' => $reviewAction === $value && $value === 'approve', 'border-red-500 bg-red-50 text-red-700' => $reviewAction === $value && $value === 'reject', 'border-slate-200' => $reviewAction !== $value])>
                                    <input type="radio" wire:model.live="reviewAction" value="{{ $value }}" class="sr-only"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <x-field :label="$reviewAction === 'reject' ? 'Reason' : 'Note (optional)'" for="rv-note" error="reviewNote">
                            <textarea wire:model="reviewNote" id="rv-note" rows="2" class="input"></textarea>
                        </x-field>
                        @error('review') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="$set('reviewId', null)" class="btn-secondary">Cancel</button>
                            <button type="submit" @class(['btn-success' => $reviewAction === 'approve', 'btn-danger' => $reviewAction === 'reject'])>{{ $reviewAction === 'reject' ? 'Reject' : 'Approve' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <x-modal wire:model="showType" :title="$typeId ? 'Edit leave type' : 'Add leave type'" max-width="max-w-md">
            <form wire:submit="saveType" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <x-field label="Name" for="t-name" error="tName" class="col-span-2"><input wire:model="tName" id="t-name" class="input" placeholder="e.g. Maternity leave"></x-field>
                    <x-field label="Code" for="t-code" error="tCode"><input wire:model="tCode" id="t-code" class="input uppercase" maxlength="6"></x-field>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <x-field label="Days per year" for="t-days" error="tDays" class="col-span-2"><input wire:model="tDays" id="t-days" type="number" min="0" class="input" placeholder="Empty = unlimited"></x-field>
                    <x-field label="Colour" for="t-color" error="tColor">
                        <select wire:model="tColor" id="t-color" class="input">
                            @foreach (\App\Models\LeaveType::COLORS as $name => $hex) <option value="{{ $name }}">{{ ucfirst($name) }}</option> @endforeach
                        </select>
                    </x-field>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input wire:model="tHalfDay" type="checkbox" class="rounded border-slate-300 text-indigo-600"> Allow half days</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input wire:model="tActive" type="checkbox" class="rounded border-slate-300 text-indigo-600"> Active (people can apply for it)</label>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
