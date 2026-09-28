<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable(['message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class MessageAttachment extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isImage(): bool
    {
        // SVG is excluded: served inline it could run scripts.
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }
}
