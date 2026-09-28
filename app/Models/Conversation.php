<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['type', 'name', 'description', 'created_by', 'direct_key', 'last_message_at'])]
class Conversation extends Model
{
    public const CHANNEL = 'channel';

    public const DIRECT = 'direct';

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot('last_read_at')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDirect(): bool
    {
        return $this->type === self::DIRECT;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /** For a direct chat, the person on the other side. */
    public function otherMember(User $user): ?User
    {
        return $this->members->firstWhere('id', '!=', $user->id);
    }

    public function displayName(User $viewer): string
    {
        return $this->isDirect() ? ($this->otherMember($viewer)?->name ?? 'Unknown') : '# '.$this->name;
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('members', fn (Builder $q) => $q->whereKey($user->id));
    }

    public static function directKey(User $a, User $b): string
    {
        return collect([$a->id, $b->id])->sort()->implode(':');
    }
}
