<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    public function __construct(private Notifier $notifier) {}

    /** Find or create the one-to-one conversation between two people. */
    public function directBetween(User $a, User $b): Conversation
    {
        return DB::transaction(function () use ($a, $b) {
            $conversation = Conversation::firstOrCreate(
                ['direct_key' => Conversation::directKey($a, $b)],
                ['type' => Conversation::DIRECT, 'created_by' => $a->id],
            );

            $conversation->members()->syncWithoutDetaching([$a->id, $b->id]);

            return $conversation;
        });
    }

    /** @param array<int> $memberIds */
    public function createChannel(User $creator, string $name, ?string $description, array $memberIds): Conversation
    {
        return DB::transaction(function () use ($creator, $name, $description, $memberIds) {
            $conversation = Conversation::create([
                'type' => Conversation::CHANNEL,
                'name' => $name,
                'description' => $description,
                'created_by' => $creator->id,
            ]);

            $conversation->members()->sync(array_unique([...array_map('intval', $memberIds), $creator->id]));

            return $conversation;
        });
    }

    /** @param array<UploadedFile> $files */
    public function send(User $sender, Conversation $conversation, ?string $body, ?int $replyToId = null, array $files = []): Message
    {
        return DB::transaction(function () use ($sender, $conversation, $body, $replyToId, $files) {
            // A reply must point at a message in the same conversation.
            if ($replyToId && ! $conversation->messages()->whereKey($replyToId)->exists()) {
                $replyToId = null;
            }

            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => filled($body) ? trim($body) : null,
                'reply_to_id' => $replyToId,
            ]);

            foreach ($files as $file) {
                $message->attachments()->create([
                    'disk' => 'local',
                    'path' => $file->store("chat/{$conversation->id}", 'local'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $this->notifier->chatMessage($message->setRelation('conversation', $conversation->load('members')));
            $this->markRead($sender, $conversation);

            return $message;
        });
    }

    public function toggleReaction(User $user, Message $message, string $emoji): void
    {
        $existing = $message->reactions()->where('user_id', $user->id)->where('emoji', $emoji)->first();

        $existing
            ? $existing->delete()
            : $message->reactions()->create(['user_id' => $user->id, 'emoji' => $emoji]);
    }

    public function togglePin(User $user, Message $message): void
    {
        $message->forceFill([
            'pinned_at' => $message->pinned_at ? null : now(),
            'pinned_by' => $message->pinned_at ? null : $user->id,
        ])->save();
    }

    public function delete(Message $message): void
    {
        foreach ($message->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }

        $message->delete();
    }

    public function deleteConversation(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $conversation->messages()->with('attachments')->get()
                ->each(fn (Message $m) => $m->attachments->each(fn ($a) => Storage::disk($a->disk)->delete($a->path)));
            $conversation->delete();
        });
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->members()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }
}
