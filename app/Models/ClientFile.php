<?php

namespace App\Models;

use Database\Factories\ClientFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable(['client_id', 'uploaded_by', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class ClientFile extends Model
{
    /** @use HasFactory<ClientFileFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const TYPES = [
        'contract' => 'Contract / agreement',
        'proposal' => 'Proposal / quote',
        'brand' => 'Brand assets (logos, fonts, colours)',
        'content' => 'Content (copy, images, videos)',
        'brief' => 'Brief / requirements',
        'invoice' => 'Invoice / billing',
        'other' => 'Other',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
}
