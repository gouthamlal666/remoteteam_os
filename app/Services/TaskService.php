<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * All task state changes go through here so the status workflow and the
 * activity timeline stay consistent regardless of which screen triggered them.
 *
 *   todo ─▶ in_progress ─▶ review ─▶ completed
 *                ▲            │
 *                └─ revision ◀┘
 */
class TaskService
{
    public function __construct(private ActivityLogger $activity, private Notifier $notifier) {}

    /** @param array<int> $assigneeIds */
    public function create(User $actor, array $data, array $assigneeIds = []): Task
    {
        return DB::transaction(function () use ($actor, $data, $assigneeIds) {
            $task = Task::create([
                ...$data,
                'created_by' => $actor->id,
                'status' => $data['status'] ?? TaskStatus::Todo->value,
            ]);

            $added = $this->syncAssignees($task, $assigneeIds);
            $this->activity->log($actor, 'task.created', "created task \"{$task->title}\"", $task);
            $this->notifier->taskAssigned($task, User::findMany($added), $actor);

            return $task;
        });
    }

    /** @param array<int> $assigneeIds */
    public function update(User $actor, Task $task, array $data, array $assigneeIds): Task
    {
        return DB::transaction(function () use ($actor, $task, $data, $assigneeIds) {
            $task->update($data);
            $added = $this->syncAssignees($task, $assigneeIds);
            $this->activity->log($actor, 'task.updated', "updated task \"{$task->title}\"", $task);
            $this->notifier->taskAssigned($task, User::findMany($added), $actor);

            return $task;
        });
    }

    /** Direct status move used by managers, and by assignees for todo ↔ in progress. */
    public function changeStatus(User $actor, Task $task, TaskStatus $status): void
    {
        if ($task->status === $status) {
            return;
        }

        $from = $task->status;

        $task->forceFill([
            'status' => $status,
            'completed_at' => $status === TaskStatus::Completed ? ($task->completed_at ?? now()) : null,
        ])->save();

        $this->activity->log($actor, 'task.status', "changed status of \"{$task->title}\" → {$status->label()}", $task, [
            'from' => $from->value,
            'to' => $status->value,
        ]);
    }

    public function submit(User $actor, Task $task, string $note): void
    {
        if (! in_array($task->status, [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Revision], true)) {
            throw new InvalidArgumentException('Only open tasks can be submitted for review.');
        }

        DB::transaction(function () use ($actor, $task, $note) {
            $task->forceFill(['status' => TaskStatus::Review, 'submitted_at' => now()])->save();
            $this->addComment($actor, $task, $note, 'submission', log: false);
            $this->activity->log($actor, 'task.submitted', "submitted \"{$task->title}\" for review", $task);
            $this->notifier->taskSubmitted($task, $actor);
        });
    }

    public function approve(User $actor, Task $task, ?string $note = null): void
    {
        $this->ensureInReview($task);

        DB::transaction(function () use ($actor, $task, $note) {
            $task->forceFill(['status' => TaskStatus::Completed, 'completed_at' => now()])->save();
            $this->addComment($actor, $task, $note ?: 'Approved.', 'approval', log: false);
            $this->activity->log($actor, 'task.approved', "approved \"{$task->title}\"", $task);
            $this->notifier->taskReviewed($task, $actor, true, $note);
        });
    }

    public function requestRevision(User $actor, Task $task, string $note): void
    {
        $this->ensureInReview($task);

        DB::transaction(function () use ($actor, $task, $note) {
            $task->forceFill([
                'status' => TaskStatus::Revision,
                'revision_count' => $task->revision_count + 1,
            ])->save();
            $this->addComment($actor, $task, $note, 'revision', log: false);
            $this->activity->log($actor, 'task.revision', "sent \"{$task->title}\" back for revision", $task);
            $this->notifier->taskReviewed($task, $actor, false, $note);
        });
    }

    public function addComment(User $actor, Task $task, string $body, string $type = 'comment', bool $log = true): TaskComment
    {
        $comment = $task->comments()->create([
            'user_id' => $actor->id,
            'type' => $type,
            'body' => $body,
        ]);

        if ($log) {
            $this->activity->log($actor, 'task.commented', "commented on \"{$task->title}\"", $task);
            $this->notifier->taskCommented($task, $actor, $body);
        }

        return $comment;
    }

    public function attach(User $actor, Task $task, UploadedFile $file): TaskAttachment
    {
        $path = $file->store("tasks/{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'user_id' => $actor->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->activity->log($actor, 'task.file', "uploaded {$attachment->original_name} to \"{$task->title}\"", $task);

        return $attachment;
    }

    public function delete(User $actor, Task $task): void
    {
        DB::transaction(function () use ($actor, $task) {
            $this->activity->log($actor, 'task.deleted', "deleted task \"{$task->title}\"", $task->project);

            foreach ($task->attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            $task->delete();
        });
    }

    /**
     * Assignees are also added to the project so they can see it.
     *
     * @return array<int> ids of newly added assignees
     */
    private function syncAssignees(Task $task, array $assigneeIds): array
    {
        $assigneeIds = array_values(array_unique(array_map('intval', $assigneeIds)));

        $changes = $task->assignees()->sync($assigneeIds);
        $task->project->members()->syncWithoutDetaching($assigneeIds);
        $task->unsetRelation('assignees');

        return $changes['attached'];
    }

    private function ensureInReview(Task $task): void
    {
        if ($task->status !== TaskStatus::Review) {
            throw new InvalidArgumentException('Only tasks in review can be approved or sent for revision.');
        }
    }
}
