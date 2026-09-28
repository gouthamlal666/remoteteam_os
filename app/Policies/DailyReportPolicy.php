<?php

namespace App\Policies;

use App\Models\DailyReport;
use App\Models\User;

class DailyReportPolicy
{
    /** Your own reports, or reports of people you manage. */
    public function view(User $user, DailyReport $report): bool
    {
        return $report->user_id === $user->id || $user->can('update', $report->user);
    }

    public function viewTeam(User $user): bool
    {
        return $user->isManager();
    }
}
