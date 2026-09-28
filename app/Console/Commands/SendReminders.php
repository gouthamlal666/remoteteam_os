<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('reminders:send')]
#[Description('Send meeting (15 min), task due-tomorrow and daily-report reminders')]
class SendReminders extends Command
{
    /** Once per working day, at the configured time, nudge people who haven't reported yet. */
    private function dailyReportReminders(Notifier $notifier): int
    {
        if (! Settings::get('work.report_reminder_enabled') || ! Settings::isWorkingDay(now())) {
            return 0;
        }

        if (now()->format('H:i') < Settings::get('work.report_reminder_time')) {
            return 0;
        }

        // Cache::add only succeeds once per day, so the reminder is sent once.
        if (! Cache::add('report-reminder:'.today()->toDateString(), true, now()->endOfDay())) {
            return 0;
        }

        $people = User::active()
            ->where('role', '!=', Role::SuperAdmin->value)
            ->whereDoesntHave('dailyReports', fn ($q) => $q->whereDate('report_date', today()))
            ->get();

        $notifier->reportReminder($people);

        return $people->count();
    }

    public function handle(Notifier $notifier): int
    {
        $meetings = Meeting::with('participants')
            ->where('status', Meeting::SCHEDULED)
            ->whereNull('reminded_at')
            ->whereBetween('starts_at', [now(), now()->addMinutes(15)])
            ->get();

        foreach ($meetings as $meeting) {
            $notifier->meetingStartingSoon($meeting);
            $meeting->forceFill(['reminded_at' => now()])->save();
        }

        $tasks = Task::with('assignees')
            ->pending()
            ->whereDate('deadline', today()->addDay())
            ->where(fn ($q) => $q->whereNull('due_reminded_on')->orWhereDate('due_reminded_on', '<', today()))
            ->get();

        foreach ($tasks as $task) {
            $notifier->taskDueSoon($task);
            $task->forceFill(['due_reminded_on' => today()])->save();
        }

        $reports = $this->dailyReportReminders($notifier);

        $this->info("Sent {$meetings->count()} meeting, {$tasks->count()} task and {$reports} daily-report reminders.");

        return self::SUCCESS;
    }
}
