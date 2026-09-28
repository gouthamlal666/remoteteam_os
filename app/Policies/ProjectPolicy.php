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

    public function delete(User $user, Project $project): bool
    {
        return $user->isSuperAdmin() || $project->owner_id === $user->id;
    }
}
