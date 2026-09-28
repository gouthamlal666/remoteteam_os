<?php

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return Task::visibleTo($user)->whereKey($task->id)->exists();
    }

    public function create(User $user, ?Project $project = null): bool
    {
        return $project ? $user->can('update', $project) : $user->isManager();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('update', $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /** Assignees can move their own task between To Do and In Progress. */
    public function updateStatus(User $user, Task $task): bool
    {
        return $this->update($user, $task) || $task->isAssignedTo($user);
    }

    public function submit(User $user, Task $task): bool
    {
        return $task->isAssignedTo($user)
            && in_array($task->status, [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Revision], true);
    }

    public function review(User $user, Task $task): bool
    {
        return $task->status === TaskStatus::Review && $this->update($user, $task);
    }

    /** Managers and assignees can attach files. */
    public function upload(User $user, Task $task): bool
    {
        return $this->update($user, $task) || $task->isAssignedTo($user);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
