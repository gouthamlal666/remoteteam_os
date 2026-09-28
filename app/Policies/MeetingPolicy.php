<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return Meeting::visibleTo($user)->whereKey($meeting->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Edit, cancel, write notes and take attendance. */
    public function update(User $user, Meeting $meeting): bool
    {
        return $user->isSuperAdmin() || $meeting->organizer_id === $user->id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    public function respond(User $user, Meeting $meeting): bool
    {
        return $meeting->participants()->whereKey($user->id)->exists();
    }
}
