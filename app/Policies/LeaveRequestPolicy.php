<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function view(User $user, LeaveRequest $request): bool
    {
        return $request->user_id === $user->id || $this->review($user, $request);
    }

    /** Super admins approve anyone; admins approve members; nobody approves their own leave. */
    public function review(User $user, LeaveRequest $request): bool
    {
        if ($request->user_id === $user->id) {
            return false;
        }

        return $user->isSuperAdmin() || ($user->isAdmin() && $request->user?->isMember());
    }

    /** Super admins can add, edit and delete anyone's leave directly. */
    public function administer(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Managing leave types, holidays and seeing everyone's leave. */
    public function manage(User $user): bool
    {
        return $user->isManager();
    }
}
