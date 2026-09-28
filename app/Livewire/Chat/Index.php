<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Chat')]
class Index extends Component
{
    use WithFileUploads;

    public const REACTIONS = ['👍', '❤️', '😂', '🎉', '👀', '✅'];

    /** Upper bound for "load older" so a tampered request can't pull an entire history at once. */
    public const MAX_HISTORY = 500;

    public ?Conversation $conversation = null;

    public string $body = '';

    public ?int $replyTo = null;

    /** @var array<TemporaryUploadedFile> */
    public array $files = [];

    public int $limit = 50;

    public string $search = '';

    public bool $showPinned = false;

    public ?int $lastSeenId = null;

    // New channel / direct message dialogs
    public bool $showNewChannel = false;

    public bool $showNewDirect = false;

    public bool $showMembers = false;

    public string $channelName = '';

    public string $channelDescription = '';

    /** @var array<int|string> */
    public array $channelMembers = [];

    /** @var array<int|string> */
    public array $addMemberIds = [];

    public function mount(?Conversation $conversation = null): void
    {
        $user = auth()->user();

        if ($conversation?->exists) {
            $this->authorize('view', $conversation);
            $this->conversation = $conversation;
        } else {
            $this->conversation = Conversation::forUser($user)->orderByDesc('last_message_at')->first();
        }

        $this->openedConversation();
    }

    public function open(int $conversationId): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorize('view', $conversation);

        $this->conversation = $conversation;
        $this->reset('body', 'replyTo', 'files', 'search', 'showPinned', 'showMembers');
        $this->limit = 50;
        $this->openedConversation();
    }

    public function startDirect(int $userId, ChatService $chat): void
    {
        $target = User::findOrFail($userId);
        $this->authorize('messageUser', [Conversation::class, $target]);

        $this->showNewDirect = false;
        $this->open($chat->directBetween(auth()->user(), $target)->id);
    }

    public function createChannel(ChatService $chat): void
    {
        $this->authorize('createChannel', Conversation::class);

        $this->validate([
            'channelName' => ['required', 'string', 'max:60'],
            'channelDescription' => ['nullable', 'string', 'max:200'],
            'channelMembers' => ['array', 'min:1'],
            'channelMembers.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ], ['channelMembers.min' => 'Pick at least one person.']);

        $conversation = $chat->createChannel(auth()->user(), $this->channelName, $this->channelDescription ?: null, $this->channelMembers);

        $this->reset('showNewChannel', 'channelName', 'channelDescription', 'channelMembers');
        $this->open($conversation->id);
    }

    public function addMembers(): void
    {
        $this->authorize('manage', $this->conversation);
        $this->validate(['addMemberIds.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)]]);

        $this->conversation->members()->syncWithoutDetaching(array_map('intval', $this->addMemberIds));
        $this->addMemberIds = [];
    }

    public function removeMember(int $userId): void
    {
        $this->authorize('manage', $this->conversation);
        abort_if($userId === auth()->id(), 422);

        $this->conversation->members()->detach($userId);
    }

    public function deleteChannel(ChatService $chat): void
    {
        $this->authorize('manage', $this->conversation);
        $name = $this->conversation->name;
        $chat->deleteConversation($this->conversation);

        $this->reset('showMembers');
        $this->conversation = null;
        $this->mount();
        $this->dispatch('notify', message: "Deleted #{$name}.");
    }

    public function leave(): void
    {
        abort_if(! $this->conversation || $this->conversation->isDirect(), 422);
        $this->conversation->members()->detach(auth()->id());
        $this->conversation = null;
        $this->mount();
    }

    public function send(ChatService $chat): void
    {
        $this->authorize('view', $this->conversation);

        $key = 'chat-send:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('body', 'You are sending messages too quickly. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000', Rule::requiredIf(empty($this->files))],
            'files' => ['array', 'max:5'],
            'files.*' => ['file', 'max:20480'],
        ], ['body.required' => 'Write a message or attach a file.']);

        $chat->send(auth()->user(), $this->conversation, $this->body, $this->replyTo, $this->files);

        $this->reset('body', 'replyTo', 'files');
        $this->dispatch('chat-scroll');
    }

    public function react(int $messageId, string $emoji, ChatService $chat): void
    {
        abort_unless(in_array($emoji, self::REACTIONS, true), 422);
        $chat->toggleReaction(auth()->user(), $this->message($messageId), $emoji);
    }

    public function togglePin(int $messageId, ChatService $chat): void
    {
        $this->authorize('pin', $this->conversation);
        $chat->togglePin(auth()->user(), $this->message($messageId));
    }

    public function deleteMessage(int $messageId, ChatService $chat): void
    {
        $message = $this->message($messageId);
        // Authors delete their own; channel owners and super admins can moderate their channels.
        abort_unless($message->user_id === auth()->id() || auth()->user()->can('manage', $this->conversation), 403);
        $chat->delete($message);
    }

    public function startReply(int $messageId): void
    {
        $this->replyTo = $this->message($messageId)->id;
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function loadOlder(): void
    {
        $this->limit = min($this->limit + 50, self::MAX_HISTORY);
    }

    /** Called by wire:poll; marks the open conversation read and scrolls on new messages. */
    public function poll(ChatService $chat): void
    {
        if (! $this->conversation) {
            return;
        }

        $latest = $this->conversation->messages()->max('id');

        if ($latest !== $this->lastSeenId) {
            $this->lastSeenId = $latest;
            $chat->markRead(auth()->user(), $this->conversation);
            $this->dispatch('chat-scroll');
        }
    }

    private function openedConversation(): void
    {
        if (! $this->conversation) {
            return;
        }

        app(ChatService::class)->markRead(auth()->user(), $this->conversation);
        $this->lastSeenId = $this->conversation->messages()->max('id');
        $this->dispatch('chat-scroll');
    }

    private function message(int $id): Message
    {
        abort_unless($this->conversation && $this->conversation->hasMember(auth()->user()), 403);

        return $this->conversation->messages()->findOrFail($id);
    }

    private function conversationList(User $user): Collection
    {
        return Conversation::forUser($user)
            ->with(['members', 'latestMessage.user:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->where('messages.user_id', '!=', $user->id)
                ->whereRaw('messages.created_at > coalesce((select last_read_at from conversation_members cm where cm.conversation_id = messages.conversation_id and cm.user_id = ?), ?)', [$user->id, '1970-01-01'])])
            ->orderByRaw('last_message_at is null, last_message_at desc')
            ->get();
    }

    public function render()
    {
        $user = auth()->user();
        $conversations = $this->conversationList($user);
        $messages = collect();
        $pinned = collect();
        $members = collect();
        $hasOlder = false;

        if ($this->conversation) {
            $this->conversation->load('members');
            $members = $this->conversation->members;

            $query = $this->conversation->messages()
                ->with(['user:id,name', 'replyTo.user:id,name', 'attachments', 'reactions.user:id,name'])
                ->when($this->search, fn ($q) => $q->where('body', 'like', '%'.$this->search.'%'));

            $this->limit = max(1, min($this->limit, self::MAX_HISTORY));
            $messages = (clone $query)->latest('id')->limit($this->limit + 1)->get();
            $hasOlder = $messages->count() > $this->limit;
            $messages = $messages->take($this->limit)->reverse()->values();

            $pinned = $this->conversation->messages()->whereNotNull('pinned_at')->with('user:id,name')->latest('pinned_at')->get();
        }

        return view('livewire.chat.index', [
            'user' => $user,
            'channels' => $conversations->where('type', Conversation::CHANNEL),
            'directs' => $conversations->where('type', Conversation::DIRECT),
            'messages' => $messages,
            'pinned' => $pinned,
            'members' => $members,
            'hasOlder' => $hasOlder,
            'replyMessage' => $this->replyTo && $this->conversation
                ? $this->conversation->messages()->with('user:id,name')->find($this->replyTo)
                : null,
            'people' => User::active()->whereKeyNot($user->id)->orderBy('name')->get()
                ->filter(fn (User $p) => $user->can('messageUser', [Conversation::class, $p]))->values(),
            'mentionables' => $members->map(fn (User $m) => ['handle' => $m->handle(), 'name' => $m->name])->values(),
        ]);
    }
}
