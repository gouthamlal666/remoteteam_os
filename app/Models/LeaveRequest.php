<?php

namespace App\Models;

use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'days', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    /** @var array<string, array{0: string, 1: string}> status => [label, badge classes] */
    public const STATUSES = [
        'pending' => ['Pending', 'bg-amber-100 text-amber-800'],
        'approved' => ['Approved', 'bg-emerald-100 text-emerald-700'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-700'],
        'cancelled' => ['Cancelled', 'bg-slate-100 text-slate-500'],
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withDefault(['name' => 'Deleted user']);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-slate-100 text-slate-600';
    }

    public function isHalfDay(): bool
    {
        return $this->half_day !== null;
    }

    public function periodLabel(): string
    {
        $range = $this->start_date->isSameDay($this->end_date)
            ? $this->start_date->format('D, d M Y')
            : $this->start_date->format('d M').' – '.$this->end_date->format('d M Y');

        return $range.($this->half_day ? ' ('.str_replace('_', ' ', $this->half_day).')' : '');
    }

    /** Pending and approved requests hold days and block overlapping requests. */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ['pending', 'approved']);
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from);
    }
}
