<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance is recorded automatically: the first time someone uses the system
 * on a day they are marked present (with their first-seen time); later activity
 * updates their last-seen time. Each person's date and start time are in their
 * own timezone. Past working days with no attendance and no approved leave are
 * absent. Admins can correct any day.
 */
class AttendanceService
{
    private ?Carbon $systemStart = null;

    /** Refresh "last seen" at most this often (keeps writes low). */
    private const TOUCH_EVERY_MINUTES = 5;

    public function __construct(private WorkCalendar $calendar) {}

    public function localToday(User $user): Carbon
    {
        return now($this->timezone($user))->startOfDay();
    }

    private function timezone(User $user): string
    {
        return $user->timezone ?: (string) config('app.timezone');
    }

    /** Livewire calls made by wire:poll on their own, not by a person. */
    private const POLL_CALLS = ['$refresh', '$commit', 'check', 'poll'];

    /**
     * True for Livewire requests that only poll in the background (a tab left
     * open overnight must not mark anyone present). Clicks, typing and page
     * loads count as real use.
     */
    public static function isBackgroundPoll(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire')) {
            return false;
        }

        foreach ((array) $request->input('components', []) as $component) {
            if (! empty($component['updates'])) {
                return false;
            }
            foreach ((array) ($component['calls'] ?? []) as $call) {
                if (! in_array($call['method'] ?? '', self::POLL_CALLS, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Called on every authenticated request (cheap: the session remembers when it last wrote).
     * Background polls only refresh "last seen" on an existing record; they never check anyone in.
     */
    public function recordActivity(User $user, bool $backgroundPoll = false): void
    {
        $today = $this->localToday($user)->toDateString();
        $session = session();
        $touched = (int) $session->get('attendance.touched_at', 0);

        if ($session->get('attendance.date') === $today && now()->timestamp - $touched < self::TOUCH_EVERY_MINUTES * 60) {
            return;
        }

        $record = $this->recordFor($user, $today);

        if ($backgroundPoll) {
            if ($record->exists) {
                $record->forceFill(['last_seen_at' => now()])->save();
            }

            return;
        }

        if (! $record->exists) {
            [$isLate, $minutesLate] = $this->lateness($user, now());
            $record->fill(['check_in_at' => now(), 'status' => 'present', 'source' => 'auto', 'is_late' => $isLate, 'minutes_late' => $minutesLate]);
        } elseif (! $record->check_in_at) {
            $record->check_in_at = now();
        }

        $record->last_seen_at = now();

        try {
            $record->save();
        } catch (UniqueConstraintViolationException) {
            // Two first-of-the-day requests at once (e.g. two devices): the other one already checked in.
            $this->recordFor($user, $today)->forceFill(['last_seen_at' => now()])->save();
        }

        $session->put('attendance.date', $today);
        $session->put('attendance.touched_at', now()->timestamp);
    }

    /** @return array{0: bool, 1: int} [is late, minutes late] against the person's start time + grace period */
    public function lateness(User $user, Carbon $checkIn): array
    {
        $start = $user->work_start ?: Settings::get('work.day_start');
        $local = $checkIn->copy()->setTimezone($this->timezone($user));
        $expected = $local->copy()->setTimeFromTimeString((string) $start);
        $minutes = (int) floor($expected->diffInMinutes($local, false));
        $grace = (int) Settings::get('work.late_grace_minutes');

        return [$minutes > $grace, max(0, $minutes)];
    }

    /**
     * Status of one person on one day.
     *
     * @param  Collection<int, LeaveRequest>  $leaves  approved leave for this person around that date
     * @return array{code: string, label: string, tone: string}
     */
    public function status(User $user, Carbon $date, ?AttendanceRecord $record, Collection $leaves): array
    {
        // Compare calendar dates as strings: $date and the user's "today" can be midnights in different timezones.
        $key = $date->toDateString();
        $today = $this->localToday($user)->toDateString();
        $day = $this->calendar->describe($date);
        $leave = $leaves->first(fn (LeaveRequest $l) => $key >= $l->start_date->toDateString() && $key <= $l->end_date->toDateString());

        // A manual correction always wins.
        if ($record?->source === 'manual') {
            return match ($record->status) {
                'absent' => ['code' => 'A', 'label' => 'Absent (marked)', 'tone' => 'absent'],
                'half_day' => ['code' => 'HD', 'label' => 'Half day (marked)', 'tone' => 'half'],
                default => ['code' => 'P', 'label' => 'Present (marked)', 'tone' => 'present'],
            };
        }

        if ($day['type'] !== 'working') {
            return $record
                ? ['code' => 'P', 'label' => 'Worked on '.($day['type'] === 'holiday' ? $day['label'] : 'a weekly off'), 'tone' => 'extra']
                : ['code' => $day['type'] === 'holiday' ? 'H' : 'W', 'label' => $day['label'], 'tone' => $day['type']];
        }

        if ($leave && ! $leave->isHalfDay()) {
            return ['code' => 'LV', 'label' => $leave->type->name, 'tone' => 'leave'];
        }

        if ($leave) {
            return ['code' => '½', 'label' => 'Half-day '.$leave->type->name.($record ? ' + present' : ''), 'tone' => 'half'];
        }

        if ($record) {
            return $record->is_late
                ? ['code' => 'L', 'label' => "Late ({$record->minutes_late} min)", 'tone' => 'late']
                : ['code' => 'P', 'label' => 'Present', 'tone' => 'present'];
        }

        if ($key < $this->trackingStart($user)->toDateString()) {
            return ['code' => '', 'label' => 'Before attendance tracking', 'tone' => 'future'];
        }

        if ($key < $today) {
            return ['code' => 'A', 'label' => 'Absent', 'tone' => 'absent'];
        }

        return $key === $today
            ? ['code' => '·', 'label' => 'Not in yet', 'tone' => 'pending']
            : ['code' => '', 'label' => 'Upcoming', 'tone' => 'future'];
    }

    /**
     * Day-by-day statuses and a summary for several people over a month.
     *
     * @param  Collection<int, User>  $people
     * @return Collection<int, array{user: User, days: array<string, array{code: string, label: string, tone: string}>, summary: array<string, int|float>}>
     */
    public function month(Collection $people, Carbon $monthStart): Collection
    {
        $from = $monthStart->copy()->startOfMonth();
        $to = $monthStart->copy()->endOfMonth();
        $ids = $people->pluck('id')->all();

        $records = AttendanceRecord::whereIn('user_id', $ids)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->get()->groupBy('user_id');
        $leaves = app(LeaveService::class)->approvedBetween($from, $to, $ids)->groupBy('user_id');

        return $people->map(function (User $user) use ($from, $to, $records, $leaves) {
            $mine = ($records[$user->id] ?? collect())->keyBy(fn ($r) => $r->date->toDateString());
            $myLeaves = $leaves[$user->id] ?? collect();
            $today = $this->localToday($user)->toDateString();
            $tracked = $this->trackingStart($user)->toDateString();
            $days = [];
            $summary = ['working' => 0, 'present' => 0.0, 'late' => 0, 'leave' => 0.0, 'absent' => 0];

            for ($d = $from->copy(); $d->lte($to); $d = $d->copy()->addDay()) {
                $key = $d->toDateString();
                $status = $this->status($user, $d, $mine[$key] ?? null, $myLeaves);
                $days[$key] = $status;

                if ($this->calendar->isWorkingDay($d) && $key <= $today && $key >= $tracked) {
                    $summary['working']++;
                }

                $this->tally($summary, $status, $mine[$key] ?? null);
            }

            $expected = max(0, $summary['working'] - $summary['leave']);
            $summary['rate'] = $expected > 0 ? (int) round(min($summary['present'], $expected) / $expected * 100) : 100;

            return ['user' => $user, 'days' => $days, 'summary' => $summary];
        });
    }

    /**
     * Adds one day to a summary. Half days count 0.5: a marked half day is 0.5
     * present; half-day leave is 0.5 leave (+ 0.5 present if they also signed in).
     *
     * @param  array<string, int|float>  $summary
     * @param  array{code: string, label: string, tone: string}  $status
     */
    private function tally(array &$summary, array $status, ?AttendanceRecord $record): void
    {
        switch ($status['tone']) {
            case 'present':
                $summary['present']++;
                break;
            case 'late':
                $summary['present']++;
                $summary['late']++;
                break;
            case 'leave':
                $summary['leave']++;
                break;
            case 'half':
                if ($status['code'] === 'HD') {
                    $summary['present'] += 0.5;
                } else {
                    $summary['leave'] += 0.5;
                    $summary['present'] += $record ? 0.5 : 0;
                }
                break;
            case 'absent':
                $summary['absent']++;
                break;
        }
    }

    /** Admin correction of one day (present / half day / absent), or back to automatic. */
    public function correct(User $admin, User $user, Carbon $date, ?string $status, ?string $note): void
    {
        $record = $this->recordFor($user, $date->toDateString());

        if ($status === null) {
            // Back to automatic: keep real sign-in times, drop the override.
            if ($record->exists && $record->check_in_at) {
                $record->update(['source' => 'auto', 'status' => 'present', 'note' => null, 'marked_by' => null]);
            } elseif ($record->exists) {
                $record->delete();
            }
        } else {
            $record->fill(['status' => $status, 'source' => 'manual', 'note' => $note ?: null, 'marked_by' => $admin->id])->save();
        }

        app(ActivityLogger::class)->log($admin, 'attendance.corrected',
            "set {$user->name}'s attendance on {$date->format('d M Y')} to ".($status ? str_replace('_', ' ', $status) : 'automatic'));
    }

    /**
     * First day someone can be marked absent: the later of the day attendance
     * tracking began (the first ever sign-in record) and the day they joined.
     */
    public function trackingStart(User $user): Carbon
    {
        $this->systemStart ??= ($first = AttendanceRecord::min('date')) ? Carbon::parse($first)->startOfDay() : today();
        $joined = $user->created_at ? Carbon::parse($user->created_at->copy()->setTimezone($this->timezone($user))->toDateString()) : null;

        return $joined && $joined->gt($this->systemStart) ? $joined : $this->systemStart;
    }

    /** whereDate keeps this working whether the driver stores dates as Y-m-d or Y-m-d H:i:s. */
    private function recordFor(User $user, string $date): AttendanceRecord
    {
        return AttendanceRecord::where('user_id', $user->id)->whereDate('date', $date)->first()
            ?? new AttendanceRecord(['user_id' => $user->id, 'date' => $date]);
    }
}
