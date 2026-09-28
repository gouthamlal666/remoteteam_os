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
    public function delete(User $user, User $target): bool
    {
        if (! $user->isSuperAdmin() || $user->is($target)) {
            return false;
        }

        return ! $target->isSuperAdmin() || User::role(Role::SuperAdmin)->count() > 1;
    }

    /** HR record (personal details, guardian contact, documents): the employee and super admins only. */
    public function viewEmployeeRecord(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || $user->is($target);
    }

    public function manageEmployeeRecord(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
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
