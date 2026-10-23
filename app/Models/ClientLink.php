<?php

namespace App\Models;

use Database\Factories\ClientLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'type', 'label', 'url'])]
class ClientLink extends Model
{
    /** @use HasFactory<ClientLinkFactory> */
    use HasFactory;

    /** @var array<string, array{0: string, 1: string}> type => [label, emoji] */
    public const TYPES = [
        'website' => ['Website', '🌐'],
        'staging' => ['Staging / test site', '🧪'],
        'admin' => ['Admin panel / CMS', '🛠️'],
        'hosting' => ['Hosting / domain', '🗄️'],
        'social' => ['Social media', '📣'],
        'drive' => ['Drive / shared folder', '📁'],
        'design' => ['Design (Figma etc.)', '🎨'],
        'repository' => ['Code repository', '💻'],
        'analytics' => ['Analytics', '📈'],
        'other' => ['Other', '🔗'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? 'Other';
    }

    public function icon(): string
    {
        return self::TYPES[$this->type][1] ?? '🔗';
    }

    public function host(): string
    {
        return (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }
}
