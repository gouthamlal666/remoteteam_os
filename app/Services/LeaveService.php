<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Leave rules:
 *  - Only working days count (Sundays, the 2nd Saturday and holidays are free).
 *  - Half days are for single-day requests on types that allow them.
 *  - No overlap with your own pending/approved requests.
 *  - Pending + approved days can't exceed the yearly allowance (unless unlimited).
 *  - Admins approve members; super admins approve anyone; nobody approves their own.
 */
class LeaveService
{
    /** How far back a request may start (e.g. sick leave applied after returning). */
    public const MAX_DAYS_IN_PAST = 30;

    public function __construct(
        private WorkCalendar $calendar,
        private Notifier $notifier,
        private ActivityLogger $activity,
    ) {}

    public function countDays(Carbon $start, Carbon $end, ?string $halfDay): float
    {
        $days = $this->calendar->countWorkingDays($start, $end);

        return $halfDay && $days === 1 ? 0.5 : (float) $days;
    }

    /** Leave days of a request (or date range) that fall inside one calendar year. */
    public function daysInYear(Carbon $start, Carbon $end, ?string $halfDay, int $year): float
    {
        $from = $start->copy()->max(Carbon::create($year, 1, 1));
        $to = $end->copy()->min(Carbon::create($year, 12, 31));

        return $from->gt($to) ? 0.0 : $this->countDays($from, $to, $halfDay);
    }

    /**
     * Allowances reset each calendar year. Leave crossing New Year is charged
     * to each year for the days that fall in it.
     *
     * @return array{allowance: ?float, used: float, pending: float, remaining: ?float}
     */
    public function balance(User $user, LeaveType $type, int $year, ?LeaveRequest $except = null): array
    {
        $requests = $user->leaveRequests()->where('leave_type_id', $type->id)->active()
            ->overlapping("{$year}-01-01", "{$year}-12-31")
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['id', 'status', 'start_date', 'end_date', 'half_day', 'days']);

        $inYear = fn (LeaveRequest $r) => $r->start_date->year === $year && $r->end_date->year === $year
            ? $r->days
            : $this->daysInYear($r->start_date, $r->end_date, $r->half_day, $year);

        $used = (float) $requests->where('status', 'approved')->sum($inYear);
        $pending = (float) $requests->where('status', 'pending')->sum($inYear);

        return [
            'allowance' => $type->days_per_year,
            'used' => $used,
            'pending' => $pending,
            'remaining' => $type->isUnlimited() ? null : max(0, $type->days_per_year - $used - $pending),
        ];
    }

    /** @param array{leave_type_id: int, start_date: string, end_date: string, half_day: ?string, reason: string} $data */
    public function apply(User $user, array $data): LeaveRequest
    {
        abort_unless($user->tracksAttendance(), 403);
        [$type, $start, $end, $halfDay, $days] = $this->check($user, $data);

        return DB::transaction(function () use ($user, $type, $start, $end, $halfDay, $days, $data) {
            $request = $user->leaveRequests()->create([
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'half_day' => $halfDay,
                'days' => $days,
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);

            $this->activity->log($user, 'leave.requested', "applied for {$days} day(s) of {$type->name}");
            $this->notifier->leaveRequested($request->load('type', 'user'), $this->approversFor($user));

            return $request;
        });
    }

    /**
     * Super admin adds or edits someone's leave directly, with any status.
     *
     * @param  array{leave_type_id: int, start_date: string, end_date: string, half_day: ?string, reason: string, status: string, review_note?: ?string}  $data
     */
    public function record(User $admin, User $employee, array $data, ?LeaveRequest $existing = null): LeaveRequest
    {
        abort_unless($admin->isSuperAdmin() && $employee->tracksAttendance(), 403);
        abort_if($existing && $existing->user_id !== $employee->id, 403);

        $status = $data['status'];
        [$type, $start, $end, $halfDay, $days] = $this->check($employee, $data, $existing, adminEntry: true, holdsDays: in_array($status, ['pending', 'approved'], true));
        $wasStatus = $existing?->status;

        $request = $existing ?? new LeaveRequest(['user_id' => $employee->id]);
        $request->fill([
            'leave_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'half_day' => $halfDay,
            'days' => $days,
            'reason' => $data['reason'],
            'status' => $status,
            'review_note' => ($data['review_note'] ?? null) ?: null,
        ]);
        if ($status !== 'pending' && $status !== $wasStatus) {
            $request->fill(['reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        }
        $request->save();
        $request->load('type', 'user');

        $this->activity->log($admin, $existing ? 'leave.edited' : 'leave.added',
            ($existing ? 'edited' : 'added')." {$employee->name}'s {$type->name} ({$request->periodLabel()}, {$status})");

        if (in_array($status, ['approved', 'rejected'], true) && $status !== $wasStatus) {
            $this->notifier->leaveReviewed($request, $admin);
        }

        return $request;
    }

    public function delete(User $admin, LeaveRequest $request): void
    {
        abort_unless($admin->isSuperAdmin(), 403);
        $this->activity->log($admin, 'leave.deleted', "deleted {$request->user->name}'s {$request->type->name} ({$request->periodLabel()})");
        $request->delete();
    }

    /**
     * Validates dates, half day, overlap and balance.
     *
     * @return array{0: LeaveType, 1: Carbon, 2: Carbon, 3: ?string, 4: float}
     */
    private function check(User $user, array $data, ?LeaveRequest $ignore = null, bool $adminEntry = false, bool $holdsDays = true): array
    {
        $type = LeaveType::query()->when(! $adminEntry, fn ($q) => $q->where('is_active', true))->findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $halfDay = ($data['half_day'] ?? null) ?: null;

        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($end->lt($start)) {
            $fail('end_date', 'The end date must be on or after the start date.');
        }
        if (! $adminEntry && $start->lt(today()->subDays(self::MAX_DAYS_IN_PAST))) {
            $fail('start_date', 'Leave can be applied for at most '.self::MAX_DAYS_IN_PAST.' days in the past.');
        }
        if ($end->gt(today()->addYear())) {
            $fail('end_date', 'Leave can be applied for up to a year ahead.');
        }
        if ($start->diffInDays($end) > 366) {
            $fail('end_date', 'A single leave can be at most a year long.');
        }
        if ($halfDay && (! $start->isSameDay($end) || ! $type->allow_half_day)) {
            $fail('half_day', $type->allow_half_day ? 'A half day must be a single date.' : "{$type->name} can't be taken as a half day.");
        }

        $days = $this->countDays($start, $end, $halfDay);
        if ($days <= 0) {
            $fail('start_date', 'Those dates are all holidays or weekly offs. No leave needed!');
        }

        if ($holdsDays) {
            $overlap = $user->leaveRequests()->active()->overlapping($start->toDateString(), $end->toDateString())
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists();
            if ($overlap) {
                $fail('start_date', ($adminEntry ? $user->name.' already has' : 'You already have').' leave for some of these dates.');
            }

            if (! $type->isUnlimited()) {
                // Check every calendar year the leave touches; when editing, the request's own days don't count.
                for ($year = $start->year; $year <= $end->year; $year++) {
                    $needed = $this->daysInYear($start, $end, $halfDay, $year);
                    $remaining = $this->balance($user, $type, $year, $ignore)['remaining'];
                    if ($needed > $remaining) {
                        $fail('leave_type_id', "Not enough {$type->name}: {$remaining} day(s) left in {$year}, this request needs {$needed}.");
                    }
                }
            }
        }

        return [$type, $start, $end, $halfDay, $days];
    }

    /** @return Collection<int, User> people who may approve $user's leave */
    public function approversFor(User $user): Collection
    {
        return User::active()->whereKeyNot($user->id)
            ->when($user->isMember(),
                fn ($q) => $q->whereIn('role', [Role::SuperAdmin->value, Role::Admin->value]),
                fn ($q) => $q->where('role', Role::SuperAdmin->value))
            ->get();
    }

    public function approve(User $reviewer, LeaveRequest $request, ?string $note): void
    {
        $this->review($reviewer, $request, 'approved', $note);
    }

    public function reject(User $reviewer, LeaveRequest $request, string $note): void
    {
        $this->review($reviewer, $request, 'rejected', $note);
    }

    private function review(User $reviewer, LeaveRequest $request, string $status, ?string $note): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['review' => 'This request has already been '.$request->status.'.']);
        }

        $request->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note ?: null]);
        $this->activity->log($reviewer, 'leave.'.$status, "{$status} {$request->user->name}'s {$request->type->name} ({$request->periodLabel()})");
        $this->notifier->leaveReviewed($request, $reviewer);
    }

    /** People can cancel pending leave, or approved leave that hasn't started yet. */
    public function canCancel(User $user, LeaveRequest $request): bool
    {
        return $request->user_id === $user->id
            && ($request->status === 'pending' || ($request->status === 'approved' && $request->start_date->isAfter(today())));
    }

    public function cancel(User $user, LeaveRequest $request): void
    {
        abort_unless($this->canCancel($user, $request), 403);
        $request->update(['status' => 'cancelled']);
        $this->activity->log($user, 'leave.cancelled', "cancelled {$request->type->name} ({$request->periodLabel()})");
    }

    /** @return Collection<int, LeaveRequest> approved leave overlapping the given dates */
    public function approvedBetween(Carbon $from, Carbon $to, ?array $userIds = null): Collection
    {
        return LeaveRequest::with('type')->where('status', 'approved')
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->get();
    }
}
