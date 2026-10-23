<?php

namespace App\Models;

use Database\Factories\ClientDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A free-form fact about a client, e.g. "Hosting provider: Hostinger". */
#[Fillable(['client_id', 'label', 'value', 'position'])]
class ClientDetail extends Model
{
    /** @use HasFactory<ClientDetailFactory> */
    use HasFactory;

    /** Common details offered as suggestions. */
    public const SUGGESTIONS = ['Hosting provider', 'Domain registrar', 'Domain expires', 'CMS / platform', 'Payment gateway', 'Email provider', 'Contract renews', 'Monthly retainer', 'Preferred contact time', 'Brand colours', 'Fonts', 'Target audience'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isLink(): bool
    {
        return (bool) preg_match('~^https?://~i', trim($this->value));
    }
}
