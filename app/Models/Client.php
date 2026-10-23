<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['name', 'legal_name', 'status', 'industry', 'website', 'email', 'phone', 'whatsapp', 'address_line', 'city', 'state', 'country', 'postal_code', 'tax_id', 'client_since', 'logo_path', 'notes', 'created_by'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /** @var array<string, array{0: string, 1: string}> status => [label, badge classes] */
    public const STATUSES = [
        'active' => ['Active', 'bg-emerald-100 text-emerald-700'],
        'prospect' => ['Prospect', 'bg-sky-100 text-sky-700'],
        'paused' => ['Paused', 'bg-amber-100 text-amber-800'],
        'former' => ['Former', 'bg-slate-100 text-slate-600'],
    ];

    protected function casts(): array
    {
        return ['client_since' => 'date'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ClientLink::class)->orderBy('type')->orderBy('label');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class)->orderBy('label');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-slate-100 text-slate-600';
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('') ?: '?';
    }

    /** Logo URL served through an authorised route, or null to show initials. */
    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? route('clients.logo', ['client' => $this->id, 'v' => substr(md5($this->logo_path), 0, 8)])
            : null;
    }

    public function fullAddress(): ?string
    {
        $parts = array_filter([$this->address_line, $this->city, $this->state, $this->postal_code, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }

    /** wa.me link for the client's WhatsApp (or phone) number. */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->whatsapp ?: $this->phone));

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    /** @return array{projects: int, open: int, overdue: int, progress: int} */
    public function workSummary(): array
    {
        $tasks = $this->tasks()->get(['tasks.id', 'tasks.status', 'tasks.deadline']);
        $done = $tasks->where('status', TaskStatus::Completed)->count();

        return [
            'projects' => $this->projects()->count(),
            'open' => $tasks->count() - $done,
            'overdue' => $tasks->filter->isOverdue()->count(),
            'progress' => $tasks->count() ? (int) round($done / $tasks->count() * 100) : 0,
        ];
    }
}
