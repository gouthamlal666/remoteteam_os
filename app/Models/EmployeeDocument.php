<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable(['user_id', 'uploaded_by', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'expires_at'])]
class EmployeeDocument extends Model
{
    public const TYPES = [
        'id_proof' => 'ID proof',
        'address_proof' => 'Address proof',
        'resume' => 'Resume / CV',
        'offer_letter' => 'Offer letter',
        'contract' => 'Contract / agreement',
        'certificate' => 'Certificate',
        'photo' => 'Photo',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withDefault(['name' => 'Deleted user']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Other';
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function expiresSoon(): bool
    {
        return $this->expires_at !== null && ! $this->isExpired() && $this->expires_at->lte(today()->addDays(30));
    }
}
