<?php

namespace App\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'days_per_year', 'color', 'allow_half_day', 'is_active', 'position'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    /** Colour name => hex, used for dots and balance bars. */
    public const COLORS = [
        'sky' => '#0ea5e9', 'rose' => '#f43f5e', 'emerald' => '#10b981', 'amber' => '#f59e0b',
        'violet' => '#8b5cf6', 'indigo' => '#6366f1', 'teal' => '#14b8a6', 'slate' => '#64748b',
    ];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'float',
            'allow_half_day' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function hex(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['slate'];
    }

    public function isUnlimited(): bool
    {
        return $this->days_per_year === null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('name');
    }
}
