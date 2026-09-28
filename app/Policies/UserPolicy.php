<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    /** Profiles are visible to everyone in the organisation. */
    public function view(User $user, User $target): bool
    {
        return true;
    }

    /** Performance numbers are only visible to the person themselves and their managers. */
    public function viewPerformance(User $user, User $target): bool
    {
        return $user->is($target) || $this->update($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && User::manageableBy($user)->whereKey($target->id)->exists();
    }

    /** Only super admins delete accounts, never their own, and never the last super admin. */
    /**
     * Super admins can delete anyone except themselves and the last super admin;
     * admins can delete members.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        if ($user->isAdmin()) {
            return $target->isMember();
        }

        return $user->isSuperAdmin()
            && (! $target->isSuperAdmin() || User::role(Role::SuperAdmin)->count() > 1);
    }

    /** HR record (personal details, guardian contact, documents): the employee and whoever manages them. */
    public function viewEmployeeRecord(User $user, User $target): bool
    {
        return $user->is($target) || $this->manageEmployeeRecord($user, $target);
    }

    /** Super admins manage everyone's record; admins manage members' records. */
    public function manageEmployeeRecord(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && $target->isMember());
    }

    /** People can change their own photo; super admins can change anyone's. */
    public function updateAvatar(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || $user->is($target);
    }

    public function assignRole(User $user, Role $role): bool
    {
        return $user->isSuperAdmin() || $role === Role::Member;
    }
}
