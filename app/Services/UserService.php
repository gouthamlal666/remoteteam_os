<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Meeting;
use App\Models\MessageAttachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function __construct(private ActivityLogger $activity, private EmployeeService $employees) {}

    /**
     * Permanently deletes $user. Work they own (projects, tasks they created,
     * meetings they organise) is handed to $actor so nothing disappears; their
     * comments, messages, files and logged time stay as "Deleted user"; their
     * personal data (reports, notifications, direct chats) is removed.
     *
     * @return array{projects: int, tasks: int, meetings: int}
     */
    public function delete(User $actor, User $user): array
    {
        return DB::transaction(function () use ($actor, $user) {
            $transferred = [
                'projects' => Project::where('owner_id', $user->id)->update(['owner_id' => $actor->id]),
                'tasks' => Task::where('created_by', $user->id)->update(['created_by' => $actor->id]),
                'meetings' => Meeting::where('organizer_id', $user->id)->update(['organizer_id' => $actor->id]),
            ];

            // Make sure the new owner can still see what they now own.
            Meeting::where('organizer_id', $actor->id)->whereDoesntHave('participants', fn ($q) => $q->whereKey($actor->id))
                ->each(fn (Meeting $m) => $m->participants()->attach($actor->id, ['response' => 'accepted']));

            // One-to-one chats are private to the pair, so they go with the person.
            $directs = Conversation::where('type', Conversation::DIRECT)->forUser($user)->get();
            foreach ($directs as $conversation) {
                $this->deleteAttachmentFiles(MessageAttachment::whereHas('message', fn ($q) => $q->where('conversation_id', $conversation->id))->get());
                $conversation->delete();
            }

            $user->notifications()->delete();
            $this->employees->purgeFiles($user);

            $this->activity->log($actor, 'user.deleted', "deleted {$user->name} ({$user->email})", null, $transferred);
            $user->delete();

            return $transferred;
        });
    }

    private function deleteAttachmentFiles($attachments): void
    {
        foreach ($attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }
}
