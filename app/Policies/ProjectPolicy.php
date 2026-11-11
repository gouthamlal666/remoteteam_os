<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return Project::visibleTo($user)->whereKey($project->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Managing a project means editing it, its members and its tasks. */
    public function update(User $user, Project $project): bool
    {
        return $user->isManager() && $this->view($user, $project);
    }

    /** Super admins, the owner, and admins (who manage every project they can see). */
    public function delete(User $user, Project $project): bool
    {
        return $user->isSuperAdmin() || $project->owner_id === $user->id || ($user->isAdmin() && $this->view($user, $project));
    }
}
