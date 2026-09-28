<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable(['task_id', 'user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class TaskAttachment extends Model
{
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'Deleted user']);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }
}
