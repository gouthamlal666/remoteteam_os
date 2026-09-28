<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(private ActivityLogger $activity, private Notifier $notifier) {}

    /** @param array<int> $participantIds */
    public function save(User $actor, ?Meeting $meeting, array $data, array $participantIds): Meeting
    {
        return DB::transaction(function () use ($actor, $meeting, $data, $participantIds) {
            $isNew = ! $meeting;
            $meeting ??= new Meeting(['organizer_id' => $actor->id]);
            $meeting->fill($data)->save();

            // The organizer always attends and has implicitly accepted.
            $ids = collect($participantIds)->map(fn ($id) => (int) $id)->push($meeting->organizer_id)->unique();
            $existing = $meeting->participants()->pluck('users.id');

            $meeting->participants()->detach($existing->diff($ids)->all());
            $added = $ids->diff($existing);
            $meeting->participants()->attach(
                $added->mapWithKeys(fn ($id) => [$id => [
                    'response' => $id === $meeting->organizer_id ? 'accepted' : 'pending',
                ]])->all()
            );
            $this->notifier->meetingInvited($meeting, User::findMany($added->all()), $actor);

            $this->activity->log($actor, $isNew ? 'meeting.created' : 'meeting.updated',
                ($isNew ? 'scheduled' : 'updated')." meeting \"{$meeting->title}\" for {$meeting->starts_at->format('d M, h:i A')}", $meeting->project);

            return $meeting;
        });
    }

    public function respond(User $user, Meeting $meeting, string $response): void
    {
        $meeting->participants()->updateExistingPivot($user->id, ['response' => $response]);
    }

    /** @param array<int, bool> $attendance user id => attended */
    public function complete(User $actor, Meeting $meeting, ?string $notes, array $attendance): void
    {
        DB::transaction(function () use ($actor, $meeting, $notes, $attendance) {
            $meeting->update(['status' => Meeting::COMPLETED, 'notes' => $notes]);

            foreach ($meeting->participants as $participant) {
                $meeting->participants()->updateExistingPivot($participant->id, [
                    'attended' => (bool) ($attendance[$participant->id] ?? false),
                ]);
            }

            $this->activity->log($actor, 'meeting.completed', "completed meeting \"{$meeting->title}\"", $meeting->project);
        });
    }

    /** Upcoming meetings tell participants it's off; past ones are removed quietly. */
    public function delete(User $actor, Meeting $meeting): void
    {
        DB::transaction(function () use ($actor, $meeting) {
            if ($meeting->isUpcoming()) {
                $this->notifier->meetingCancelled($meeting->load('participants'), $actor, deleted: true);
            }

            $this->activity->log($actor, 'meeting.deleted', "deleted meeting \"{$meeting->title}\"", $meeting->project);
            $meeting->delete();
        });
    }

    public function cancel(User $actor, Meeting $meeting): void
    {
        $meeting->update(['status' => Meeting::CANCELLED]);
        $this->notifier->meetingCancelled($meeting->load('participants'), $actor);
        $this->activity->log($actor, 'meeting.cancelled', "cancelled meeting \"{$meeting->title}\"", $meeting->project);
    }
}
