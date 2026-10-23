<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use App\Services\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Attendance')]
class Index extends Component
{
    /** mine | today | month | holidays */
    #[Url]
    public string $tab = 'mine';

    #[Url(except: '')]
    public string $month = '';

    #[Url(except: '')]
    public string $year = '';

    // Correction dialog
    public bool $showCorrection = false;

    public ?int $corrUserId = null;

    public string $corrDate = '';

    public string $corrStatus = 'auto';

    public string $corrNote = '';

    // Holiday dialog
    public bool $showHoliday = false;

    public ?int $holidayId = null;

    public string $hDate = '';

    public string $hName = '';

    public string $hType = 'kerala';

    public bool $hOptional = false;

    public bool $hNeedsConfirmation = false;

    public function mount(): void
    {
        $user = auth()->user();
        $allowed = array_merge($user->tracksAttendance() ? ['mine'] : [], $user->isManager() ? ['today', 'month'] : [], ['holidays']);
        if (! in_array($this->tab, $allowed, true)) {
            $this->tab = $allowed[0];
        }
    }

    private function monthStart(): Carbon
    {
        return rescue(fn () => Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth(), today()->startOfMonth(), false);
    }

    private function holidayYear(): int
    {
        return ctype_digit($this->year) ? (int) $this->year : today()->year;
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart()->addMonths($by)->format('Y-m');
    }

    public function shiftYear(int $by): void
    {
        $this->year = (string) ($this->holidayYear() + $by);
    }

    // ── Corrections ────────────────────────────────────────────

    private function correctableUser(int $userId): User
    {
        $target = User::findOrFail($userId);
        abort_unless(auth()->user()->isManager() && $target->tracksAttendance() && ! $target->is(auth()->user()) && auth()->user()->can('update', $target), 403);

        return $target;
    }

    public function openCorrection(int $userId, string $date): void
    {
        $target = $this->correctableUser($userId);
        $day = Carbon::parse($date);
        $record = AttendanceRecord::where('user_id', $target->id)->whereDate('date', $day)->first();

        $this->corrUserId = $target->id;
        $this->corrDate = $day->toDateString();
        $this->corrStatus = $record?->source === 'manual' ? $record->status : 'auto';
        $this->corrNote = (string) $record?->note;
        $this->resetValidation();
        $this->showCorrection = true;
    }

    public function saveCorrection(AttendanceService $attendance): void
    {
        $target = $this->correctableUser((int) $this->corrUserId);
        $this->validate([
            'corrDate' => ['required', 'date', 'before_or_equal:'.today()->addDay()->toDateString()],
            'corrStatus' => ['required', Rule::in(['auto', 'present', 'half_day', 'absent'])],
            'corrNote' => [Rule::requiredIf($this->corrStatus !== 'auto'), 'nullable', 'string', 'max:255'],
        ], ['corrNote.required' => 'Add a short reason for the correction.']);

        $attendance->correct(auth()->user(), $target, Carbon::parse($this->corrDate), $this->corrStatus === 'auto' ? null : $this->corrStatus, $this->corrNote);
        $this->reset('showCorrection', 'corrUserId', 'corrDate', 'corrStatus', 'corrNote');
        $this->dispatch('notify', message: 'Attendance updated.');
    }

    // ── Holidays ───────────────────────────────────────────────

    public function editHoliday(?int $id = null): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $holiday = $id ? Holiday::findOrFail($id) : null;

        $this->holidayId = $holiday?->id;
        $this->hDate = $holiday?->date->toDateString() ?? '';
        $this->hName = (string) $holiday?->name;
        $this->hType = $holiday?->type ?? 'kerala';
        $this->hOptional = (bool) $holiday?->is_optional;
        $this->hNeedsConfirmation = (bool) $holiday?->needs_confirmation;
        $this->resetValidation();
        $this->showHoliday = true;
    }

    public function saveHoliday(ActivityLogger $activity, WorkCalendar $calendar): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $data = $this->validate([
            'hDate' => ['required', 'date'],
            'hName' => ['required', 'string', 'max:150', Rule::unique('holidays', 'name')->where(fn ($q) => $q->whereDate('date', $this->hDate))->ignore($this->holidayId)],
            'hType' => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'hOptional' => ['boolean'],
            'hNeedsConfirmation' => ['boolean'],
        ], ['hName.unique' => 'That holiday is already on this date.'], ['hDate' => 'date', 'hName' => 'name']);

        Holiday::updateOrCreate(['id' => $this->holidayId], [
            'date' => $data['hDate'], 'name' => $data['hName'], 'type' => $data['hType'],
            'is_optional' => $data['hOptional'], 'needs_confirmation' => $data['hNeedsConfirmation'],
        ]);
        $calendar->forgetCache();
        $activity->log(auth()->user(), 'holiday.saved', "saved holiday {$data['hName']} on ".Carbon::parse($data['hDate'])->format('d M Y'));

        $this->year = (string) Carbon::parse($data['hDate'])->year;
        $this->reset('showHoliday', 'holidayId', 'hDate', 'hName', 'hOptional', 'hNeedsConfirmation');
        $this->dispatch('notify', message: 'Holiday saved.');
    }

    public function confirmHoliday(int $id): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        Holiday::findOrFail($id)->update(['needs_confirmation' => false]);
    }

    public function deleteHoliday(int $id, ActivityLogger $activity): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $holiday = Holiday::findOrFail($id);
        $activity->log(auth()->user(), 'holiday.deleted', "removed holiday {$holiday->name} ({$holiday->date->format('d M Y')})");
        $holiday->delete();
    }

    /** Copies this year's fixed-date holidays (not lunar festivals) into next year. */
    public function copyFixedHolidays(): void
    {
        abort_unless(auth()->user()->isManager(), 403);
        $year = $this->holidayYear();
        $copied = 0;

        foreach (Holiday::whereYear('date', $year)->where('needs_confirmation', false)->whereIn('type', ['national', 'kerala', 'company'])->get() as $h) {
            $date = $h->date->copy()->addYear();
            // Easter-based dates move every year, so they are left out.
            if (in_array($h->name, ['Good Friday', 'Maundy Thursday', 'Easter'], true)) {
                continue;
            }
            if (Holiday::whereDate('date', $date)->where('name', $h->name)->doesntExist()) {
                Holiday::create(['date' => $date->toDateString(), 'name' => $h->name, 'type' => $h->type, 'is_optional' => $h->is_optional]);
                $copied++;
            }
        }

        $this->year = (string) ($year + 1);
        $this->dispatch('notify', message: "Copied {$copied} fixed-date holidays to ".($year + 1).'. Add that year\'s festivals separately.');
    }

    public function render(AttendanceService $attendance, LeaveService $leave, WorkCalendar $calendar)
    {
        $user = auth()->user();
        $month = $this->monthStart();
        $data = ['user' => $user, 'monthStart' => $month, 'calendar' => $calendar];

        if ($this->tab === 'mine' && $user->tracksAttendance()) {
            $data['mine'] = $attendance->month(collect([$user]), $month)->first();
            $data['todayRecord'] = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $attendance->localToday($user))->first();
            $data['todayInfo'] = $calendar->describe($attendance->localToday($user));
        }

        if ($this->tab === 'today' && $user->isManager()) {
            $people = User::employees()->orderBy('name')->get();
            $records = AttendanceRecord::whereIn('user_id', $people->pluck('id'))
                ->whereDate('date', '>=', today()->subDay())->whereDate('date', '<=', today()->addDay())->get();
            $leaves = $leave->approvedBetween(today()->subDay(), today()->addDay(), $people->pluck('id')->all())->groupBy('user_id');

            $data['register'] = $people->map(function (User $p) use ($attendance, $records, $leaves) {
                $day = Carbon::parse($attendance->localToday($p)->toDateString());
                $record = $records->first(fn ($r) => $r->user_id === $p->id && $r->date->toDateString() === $day->toDateString());

                return ['user' => $p, 'record' => $record, 'status' => $attendance->status($p, $day, $record, $leaves[$p->id] ?? collect()), 'canCorrect' => $p->tracksAttendance() && ! $p->is(auth()->user()) && auth()->user()->can('update', $p), 'date' => $day->toDateString()];
            });
        }

        if ($this->tab === 'month' && $user->isManager()) {
            $data['sheet'] = $attendance->month(User::employees()->orderBy('name')->get(), $month);
        }

        if ($this->tab === 'holidays') {
            $data['holidays'] = Holiday::whereYear('date', $this->holidayYear())->orderBy('date')->get();
            $data['holidayYear'] = $this->holidayYear();
        }

        $data['upcoming'] = Holiday::where('is_optional', false)->whereDate('date', '>=', today())->orderBy('date')->limit(4)->get();
        $data['corrUser'] = $this->corrUserId ? User::find($this->corrUserId) : null;

        return view('livewire.attendance.index', $data);
    }
}
