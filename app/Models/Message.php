<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;

#[Fillable(['conversation_id', 'user_id', 'reply_to_id', 'body', 'pinned_at', 'pinned_by', 'edited_at'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'Deleted user']);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * Escaped body with @mentions highlighted and URLs linked. Mentions use the
     * first name or "first.last" handle (see User::handle()).
     */
    public function renderedBody(): HtmlString
    {
        $html = e((string) $this->body);

        $html = preg_replace(
            '~(https?://[^\s<]+)~',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="underline decoration-dotted">$1</a>',
            $html
        );

        $html = preg_replace(
            '~(^|\s)@([a-z0-9._-]+)~i',
            '$1<span class="rounded bg-indigo-100 px-1 font-medium text-indigo-700">@$2</span>',
            $html
        );

        return new HtmlString(nl2br($html, false));
    }

    /** @return array<string> lower-cased handles mentioned in the body */
    public function mentionedHandles(): array
    {
        preg_match_all('~(?:^|\s)@([a-z0-9._-]+)~i', (string) $this->body, $m);

        return array_map('strtolower', $m[1]);
    }
}
