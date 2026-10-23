<?php

namespace App\Livewire\Leave;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LeaveService;
use App\Services\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Leave')]
class Index extends Component
{
    use WithPagination;

    /** mine | approvals | all | types */
    #[Url]
    public string $tab = 'mine';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $person = '';

    // Apply dialog
    public bool $showApply = false;

    public ?int $leave_type_id = null;

    public string $start_date = '';

    public string $end_date = '';

    public string $half_day = '';

    public string $reason = '';

    // Super admin: add / edit anyone's leave
    public ?int $editingId = null;

    public string $forUserId = '';

    public string $entryStatus = 'approved';

    public string $entryNote = '';

    // Review dialog
    public ?int $reviewId = null;

    public string $reviewAction = 'approve';

    public string $reviewNote = '';

    // Leave type dialog
    public bool $showType = false;

    public ?int $typeId = null;

    public string $tName = '';

    public string $tCode = '';

    public string $tDays = '';

    public string $tColor = 'indigo';

    public bool $tHalfDay = true;

    public bool $tActive = true;

    public function mount(): void
    {
        $user = auth()->user();
        $allowed = array_merge($user->tracksAttendance() ? ['mine'] : [], $user->isManager() ? ['approvals', 'all', 'types'] : []);
        if (! in_array($this->tab, $allowed, true)) {
            $this->tab = $allowed[0];
        }
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['tab', 'status', 'person'], true)) {
            $this->resetPage();
        }
    }

    // ── Applying ───────────────────────────────────────────────

    public function openApply(?string $date = null): void
    {
        abort_unless(auth()->user()->tracksAttendance(), 403);
        $this->reset('leave_type_id', 'half_day', 'reason', 'editingId', 'forUserId', 'entryStatus', 'entryNote');
        $this->start_date = $this->end_date = $date ?? today()->toDateString();
        $this->leave_type_id = LeaveType::active()->value('id');
        $this->resetValidation();
        $this->showApply = true;
    }

    public function updatedStartDate(): void
    {
        if ($this->start_date && (! $this->end_date || $this->end_date < $this->start_date)) {
            $this->end_date = $this->start_date;
        }
    }

    public function apply(LeaveService $leave): void
    {
        $data = $this->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'half_day' => ['nullable', Rule::in(['', 'first_half', 'second_half'])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], ['leave_type_id' => 'leave type']);

        $leave->apply(auth()->user(), $data);

        $this->reset('showApply', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'reason');
        $this->dispatch('notify', message: 'Leave applied. You\'ll be notified once it\'s reviewed.');
    }

    public function cancel(int $id, LeaveService $leave): void
    {
        $leave->cancel(auth()->user(), LeaveRequest::findOrFail($id));
        $this->dispatch('notify', message: 'Leave cancelled.');
    }

    // ── Super admin: add, edit, delete ─────────────────────────

    public function openEntry(?int $id = null): void
    {
        $this->authorize('administer', LeaveRequest::class);
        $request = $id ? LeaveRequest::findOrFail($id) : null;

        $this->editingId = $request?->id;
        $this->forUserId = (string) ($request?->user_id ?? '');
        $this->leave_type_id = $request?->leave_type_id ?? LeaveType::active()->value('id');
        $this->start_date = $request?->start_date->toDateString() ?? today()->toDateString();
        $this->end_date = $request?->end_date->toDateString() ?? $this->start_date;
        $this->half_day = (string) $request?->half_day;
        $this->reason = (string) $request?->reason;
        $this->entryStatus = $request?->status ?? 'approved';
        $this->entryNote = (string) $request?->review_note;
        $this->resetValidation();
        $this->showApply = true;
    }

    public function saveEntry(LeaveService $leave): void
    {
        $this->authorize('administer', LeaveRequest::class);
        $data = $this->validate([
            // New leave only for active employees; existing leave stays editable after someone is deactivated.
            'forUserId' => $this->editingId
                ? ['required', Rule::in([(string) LeaveRequest::whereKey($this->editingId)->value('user_id')])]
                : ['required', Rule::exists('users', 'id')->where('is_active', true)->whereNot('role', 'super_admin')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'half_day' => ['nullable', Rule::in(['', 'first_half', 'second_half'])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'entryStatus' => ['required', Rule::in(array_keys(LeaveRequest::STATUSES))],
            'entryNote' => ['nullable', 'string', 'max:500'],
        ], [], ['forUserId' => 'employee', 'leave_type_id' => 'leave type', 'entryStatus' => 'status']);

        $existing = $this->editingId ? LeaveRequest::findOrFail($this->editingId) : null;
        $leave->record(auth()->user(), User::findOrFail($data['forUserId']), $data + ['status' => $data['entryStatus'], 'review_note' => $data['entryNote']], $existing);

        $this->reset('showApply', 'editingId', 'forUserId', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'reason', 'entryStatus', 'entryNote');
        $this->dispatch('notify', message: $existing ? 'Leave updated.' : 'Leave added.');
    }

    public function deleteLeave(int $id, LeaveService $leave): void
    {
        $this->authorize('administer', LeaveRequest::class);
        $leave->delete(auth()->user(), LeaveRequest::with('user', 'type')->findOrFail($id));
        $this->dispatch('notify', message: 'Leave deleted.');
    }

    // ── Reviewing ──────────────────────────────────────────────

    public function openReview(int $id, string $action): void
    {
        $this->authorize('review', LeaveRequest::findOrFail($id));
        $this->reviewId = $id;
        $this->reviewAction = $action === 'reject' ? 'reject' : 'approve';
        $this->reviewNote = '';
        $this->resetValidation();
    }

    public function saveReview(LeaveService $leave): void
    {
        $request = LeaveRequest::with('user', 'type')->findOrFail($this->reviewId);
        $this->authorize('review', $request);
        $this->validate([
            'reviewNote' => [Rule::requiredIf($this->reviewAction === 'reject'), 'nullable', 'string', 'max:500'],
        ], ['reviewNote.required' => 'Let them know why it was rejected.']);

        $this->reviewAction === 'reject'
            ? $leave->reject(auth()->user(), $request, $this->reviewNote)
            : $leave->approve(auth()->user(), $request, $this->reviewNote);

        $this->reset('reviewId', 'reviewNote');
        $this->dispatch('notify', message: 'Leave '.($request->fresh()->status).'.');
    }

    // ── Leave types ────────────────────────────────────────────

    public function editType(?int $id = null): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $type = $id ? LeaveType::findOrFail($id) : null;

        $this->typeId = $type?->id;
        $this->tName = (string) $type?->name;
        $this->tCode = (string) $type?->code;
        $this->tDays = $type?->days_per_year === null ? ($type ? '' : '12') : (string) $type->days_per_year;
        $this->tColor = $type?->color ?? 'indigo';
        $this->tHalfDay = $type ? $type->allow_half_day : true;
        $this->tActive = $type ? $type->is_active : true;
        $this->resetValidation();
        $this->showType = true;
    }

    public function saveType(ActivityLogger $activity): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $this->tCode = strtoupper(trim($this->tCode));
        $data = $this->validate([
            'tName' => ['required', 'string', 'max:60'],
            'tCode' => ['required', 'alpha_num', 'max:6', Rule::unique('leave_types', 'code')->ignore($this->typeId)],
            'tDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tColor' => ['required', Rule::in(array_keys(LeaveType::COLORS))],
            'tHalfDay' => ['boolean'],
            'tActive' => ['boolean'],
        ], [], ['tName' => 'name', 'tCode' => 'code', 'tDays' => 'days per year', 'tColor' => 'colour']);

        $type = LeaveType::updateOrCreate(['id' => $this->typeId], [
            'name' => $data['tName'], 'code' => $data['tCode'],
            'days_per_year' => $data['tDays'] === '' || $data['tDays'] === null ? null : (int) $data['tDays'],
            'color' => $data['tColor'], 'allow_half_day' => $data['tHalfDay'], 'is_active' => $data['tActive'],
            'position' => $this->typeId ? LeaveType::find($this->typeId)->position : (int) LeaveType::max('position') + 1,
        ]);
        $activity->log(auth()->user(), 'leave_type.saved', "saved leave type {$type->name}");

        $this->reset('showType', 'typeId');
        $this->dispatch('notify', message: 'Leave type saved.');
    }

    public function render(LeaveService $leave, WorkCalendar $calendar)
    {
        $user = auth()->user();
        $year = today()->year;
        $types = LeaveType::active()->get();
        $data = ['user' => $user, 'types' => $types, 'year' => $year];

        $data['balances'] = $user->tracksAttendance() ? $types->map(fn (LeaveType $t) => ['type' => $t] + $leave->balance($user, $t, $year)) : collect();
        $data['myRequests'] = $this->tab === 'mine' && $user->tracksAttendance() ? $user->leaveRequests()->with('type', 'reviewer')->latest('start_date')->paginate(10) : null;

        if ($user->isManager()) {
            // Only requests this person is allowed to approve.
            $data['pending'] = LeaveRequest::with('user', 'type')->where('status', 'pending')->where('user_id', '!=', $user->id)
                ->orderBy('start_date')->get()->filter(fn ($r) => $user->can('review', $r))->values();
            // Balance before each request, for the approver.
            $data['pendingBalances'] = $this->tab === 'approvals'
                ? $data['pending']->mapWithKeys(fn ($r) => [$r->id => $leave->balance($r->user, $r->type, $r->start_date->year, $r)])
                : collect();

            if ($this->tab === 'all') {
                $data['requests'] = LeaveRequest::with('user', 'type', 'reviewer')
                    ->when($this->status, fn ($q) => $q->where('status', $this->status))
                    ->when(ctype_digit($this->person), fn ($q) => $q->where('user_id', $this->person))
                    ->latest('start_date')->paginate(15);
            }
            $data['people'] = User::employees()->orderBy('name')->get(['id', 'name', 'role']);
            if ($this->tab === 'types') {
                $data['allTypes'] = LeaveType::orderBy('position')->get();
            }
        }

        // Who's off over the next two weeks (everyone can see this; reasons stay private).
        $data['whosOff'] = $leave->approvedBetween(today(), today()->addDays(13))->load('user')
            ->filter(fn ($r) => $r->user?->is_active)->sortBy('start_date')->values();

        $data['reviewing'] = $this->reviewId ? LeaveRequest::with('user', 'type')->find($this->reviewId) : null;
        $data['preview'] = $this->showApply ? $this->previewDays($leave, $calendar) : null;

        // Balances of the person a super admin is adding leave for.
        $for = $this->showApply && ctype_digit($this->forUserId) ? User::find($this->forUserId) : null;
        $data['entryBalances'] = $for ? $types->map(fn (LeaveType $t) => ['type' => $t] + $leave->balance($for, $t, $year)) : collect();

        return view('livewire.leave.index', $data);
    }

    /** Live "this will use N days" hint for the apply form. */
    private function previewDays(LeaveService $leave, WorkCalendar $calendar): ?array
    {
        $start = rescue(fn () => Carbon::parse($this->start_date), null, false);
        $end = rescue(fn () => Carbon::parse($this->end_date), null, false);
        if (! $start || ! $end || $end->lt($start) || $start->diffInDays($end) > 366) {
            return null;
        }

        $skipped = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (! $calendar->isWorkingDay($d)) {
                $skipped->push($d->format('D d M').' · '.$calendar->describe($d)['label']);
            }
        }

        return ['days' => $leave->countDays($start, $end, $this->half_day ?: null), 'skipped' => $skipped];
    }
}
