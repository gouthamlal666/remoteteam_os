<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'to', 'type', 'body', 'driver', 'status', 'provider_id', 'error'])]
class WhatsappMessage extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => '—']);
    }
}
