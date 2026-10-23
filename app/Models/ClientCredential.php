<?php

namespace App\Models;

use Database\Factories\ClientCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A login kept for a client (hosting, CMS, social account…). The password and
 * notes are encrypted at rest and hidden from array/JSON output, so they never
 * reach the browser unless explicitly revealed.
 */
#[Fillable(['client_id', 'category', 'label', 'url', 'username', 'password', 'notes', 'created_by'])]
#[Hidden(['password', 'notes'])]
class ClientCredential extends Model
{
    /** @use HasFactory<ClientCredentialFactory> */
    use HasFactory;

    /** @var array<string, array{0: string, 1: string}> category => [label, icon] */
    public const CATEGORIES = [
        'hosting' => ['Hosting / server', '🗄️'],
        'domain' => ['Domain', '🌐'],
        'cms' => ['Website admin / CMS', '🛠️'],
        'email' => ['Email', '✉️'],
        'social' => ['Social media', '📣'],
        'analytics' => ['Analytics / ads', '📈'],
        'payment' => ['Payments', '💳'],
        'database' => ['Database / FTP', '🔌'],
        'other' => ['Other', '🔑'],
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category][0] ?? 'Other';
    }

    public function icon(): string
    {
        return self::CATEGORIES[$this->category][1] ?? '🔑';
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault(['name' => 'Deleted user']);
    }
}
