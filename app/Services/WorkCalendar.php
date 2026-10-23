<?php

namespace App\Services;

use App\Models\Holiday;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which days are working days. Weekly offs come from System settings
 * (default: Sundays off, plus the 2nd Saturday of every month); holidays
 * come from the holidays table (optional/restricted holidays are not days off).
 */
class WorkCalendar
{
    /** @var array<int, array<string, Holiday>> year => [Y-m-d => holiday] */
    private array $holidays = [];

    /** 1-based position of this weekday within its month (e.g. 2 for the 2nd Saturday). */
    public static function weekOfMonth(CarbonInterface $date): int
    {
        return intdiv($date->day - 1, 7) + 1;
    }

    public function isWeeklyOff(CarbonInterface $date): bool
    {
        if (! in_array(strtolower($date->format('D')), Settings::workingDays(), true)) {
            return true;
        }

        return $date->isSaturday()
            && in_array(self::weekOfMonth($date), array_map('intval', (array) Settings::get('work.saturday_offs')), true);
    }

    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        $year = $date->year;

        $this->holidays[$year] ??= Holiday::whereYear('date', $year)->where('is_optional', false)->get()
            ->keyBy(fn (Holiday $h) => $h->date->toDateString())->all();

        return $this->holidays[$year][$date->toDateString()] ?? null;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeeklyOff($date) && ! $this->holidayOn($date);
    }

    /** @return array{type: string, label: string} type: working | weekly_off | holiday */
    public function describe(CarbonInterface $date): array
    {
        if ($holiday = $this->holidayOn($date)) {
            return ['type' => 'holiday', 'label' => $holiday->name];
        }

        if ($this->isWeeklyOff($date)) {
            return ['type' => 'weekly_off', 'label' => $date->isSaturday() ? self::ordinal(self::weekOfMonth($date)).' Saturday off' : $date->format('l').' off'];
        }

        return ['type' => 'working', 'label' => 'Working day'];
    }

    /** @return Collection<int, Carbon> working dates from $from to $to inclusive */
    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $days = collect();
        for ($day = Carbon::parse($from)->startOfDay(); $day->lte($to); $day = $day->copy()->addDay()) {
            if ($this->isWorkingDay($day)) {
                $days->push($day->copy());
            }
        }

        return $days;
    }

    public function countWorkingDays(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->workingDaysBetween($from, $to)->count();
    }

    public function forgetCache(): void
    {
        $this->holidays = [];
    }

    public static function ordinal(int $n): string
    {
        return $n.match (true) {
            in_array($n % 100, [11, 12, 13], true) => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };
    }
}
