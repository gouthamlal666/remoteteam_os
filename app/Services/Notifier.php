<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Decides who hears about what. The person who triggered an event is never
 * notified about their own action.
 */
class Notifier
{
    /** @param iterable<User> $recipients */
    private function send(iterable $recipients, ?User $actor, Alert $alert): void
    {
        $users = collect($recipients)->filter()
            ->unique('id')
            ->reject(fn (User $u) => $actor && $u->is($actor))
            ->filter(fn (User $u) => $u->is_active);

        if ($users->isNotEmpty()) {
            Notification::send($users, $alert);
        }
    }

    /** @param iterable<User> $assignees */
    public function taskAssigned(Task $task, iterable $assignees, User $actor): void
    {
        $due = $task->deadline ? ' · due '.$task->deadline->format('d M') : '';
        $this->send($assignees, $actor, new Alert(
            'task.assigned', 'New task assigned', "{$actor->name} assigned you \"{$task->title}\"{$due}.",
            route('tasks.show', $task), '📋', mail: true,
        ));
    }

    public function taskSubmitted(Task $task, User $actor): void
    {
        $this->send([$task->creator, $task->project->owner], $actor, new Alert(
            'task.submitted', 'Task ready for review', "{$actor->name} submitted \"{$task->title}\" for review.",
            route('tasks.show', $task), '📤', mail: true,
        ));
    }

    public function taskReviewed(Task $task, User $actor, bool $approved, ?string $note): void
    {
        $this->send($task->assignees, $actor, new Alert(
            $approved ? 'task.approved' : 'task.revision',
            $approved ? 'Task approved' : 'Task sent for revision',
            $approved
                ? "{$actor->name} approved \"{$task->title}\"."
                : "{$actor->name} requested changes on \"{$task->title}\": ".Str::limit((string) $note, 120),
            route('tasks.show', $task), $approved ? '✅' : '🔁', mail: true,
        ));
    }

    public function taskCommented(Task $task, User $actor, string $body): void
    {
        $mentioned = $this->mentionedUsers($body);

        $this->send($mentioned, $actor, new Alert(
            'task.mentioned', 'You were mentioned', "{$actor->name} mentioned you on \"{$task->title}\": ".Str::limit($body, 120),
            route('tasks.show', $task), '💬',
        ));

        $this->send($task->assignees->concat([$task->creator])->reject(fn ($u) => $mentioned->contains('id', $u->id)), $actor, new Alert(
            'task.commented', 'New comment', "{$actor->name} commented on \"{$task->title}\": ".Str::limit($body, 120),
            route('tasks.show', $task), '💬',
        ));
    }

    public function taskDueSoon(Task $task): void
    {
        $this->send($task->assignees, null, new Alert(
            'task.due', 'Task due tomorrow', "\"{$task->title}\" is due tomorrow ({$task->deadline->format('d M')}).",
            route('tasks.show', $task), '⏰', mail: true,
        ));
    }

    public function chatMessage(Message $message): void
    {
        $conversation = $message->conversation;
        $sender = $message->user;
        $url = route('chat', $conversation);
        $excerpt = Str::limit($message->body ?? '📎 Attachment', 120);

        $mentioned = $this->mentionedUsers((string) $message->body)
            ->filter(fn (User $u) => $conversation->members->contains('id', $u->id));

        $this->send($mentioned, $sender, new Alert(
            'chat.mention', "{$sender->name} mentioned you", ($conversation->isDirect() ? '' : "#{$conversation->name}: ").$excerpt, $url, '💬',
        ));

        // Direct messages notify only on the first unread message, not every line.
        if ($conversation->isDirect()) {
            $recipient = $conversation->otherMember($sender);
            $lastRead = $recipient ? $conversation->members->firstWhere('id', $recipient->id)?->pivot->last_read_at : null;
            $alreadyUnread = $recipient && $conversation->messages()
                ->where('user_id', $sender->id)->whereKeyNot($message->id)
                ->when($lastRead, fn ($q) => $q->where('created_at', '>', $lastRead))
                ->exists();

            if ($recipient && ! $alreadyUnread && ! $mentioned->contains('id', $recipient->id)) {
                $this->send([$recipient], $sender, new Alert('chat.direct', "Message from {$sender->name}", $excerpt, $url, '✉️'));
            }
        }
    }

    /** @param iterable<User> $invitees */
    public function meetingInvited(Meeting $meeting, iterable $invitees, User $actor): void
    {
        $this->send($invitees, $actor, new Alert(
            'meeting.invited', 'Meeting invitation',
            "{$actor->name} invited you to \"{$meeting->title}\" on {$meeting->starts_at->format('D d M, h:i A')}.",
            route('meetings.show', $meeting), '📅', mail: true,
        ));
    }

    public function meetingStartingSoon(Meeting $meeting): void
    {
        $going = $meeting->participants->filter(fn (User $u) => $u->pivot->response !== 'declined');

        $this->send($going, null, new Alert(
            'meeting.soon', 'Meeting starts soon',
            "\"{$meeting->title}\" starts at {$meeting->starts_at->format('h:i A')}.",
            route('meetings.show', $meeting), '🎥', mail: true,
        ));
    }

    public function meetingCancelled(Meeting $meeting, User $actor, bool $deleted = false): void
    {
        $this->send($meeting->participants, $actor, new Alert(
            'meeting.cancelled', 'Meeting cancelled',
            "{$actor->name} cancelled \"{$meeting->title}\" ({$meeting->starts_at->format('D d M, h:i A')}).",
            $deleted ? route('meetings.index') : route('meetings.show', $meeting), '🚫', mail: true,
        ));
    }

    /** @param iterable<User> $users people who haven't submitted today's report */
    public function reportReminder(iterable $users): void
    {
        $this->send($users, null, new Alert(
            'report.reminder', "Don't forget today's report",
            'What did you complete, what are you working on, any blockers? It only takes a minute.',
            route('reports.index'), '📝', mail: true,
        ));
    }

    /** Resolves "@first.last" handles in a text to active users. */
    public function mentionedUsers(string $text): Collection
    {
        preg_match_all('~(?:^|\s)@([a-z0-9._-]+)~i', $text, $m);
        $handles = array_unique(array_map('strtolower', $m[1]));

        if (! $handles) {
            return collect();
        }

        return User::active()->get()->filter(fn (User $u) => in_array($u->handle(), $handles, true))->values();
    }
}
