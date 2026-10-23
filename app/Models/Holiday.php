<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'type', 'is_optional', 'needs_confirmation'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /** @var array<string, array{0: string, 1: string}> type => [label, badge classes] */
    public const TYPES = [
        'national' => ['National', 'bg-sky-100 text-sky-700'],
        'kerala' => ['Kerala', 'bg-emerald-100 text-emerald-700'],
        'festival' => ['Festival', 'bg-violet-100 text-violet-700'],
        'company' => ['Company', 'bg-indigo-100 text-indigo-700'],
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_optional' => 'boolean',
            'needs_confirmation' => 'boolean',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    public function typeBadge(): string
    {
        return self::TYPES[$this->type][1] ?? 'bg-slate-100 text-slate-600';
    }
}
