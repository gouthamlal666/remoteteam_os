<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'check_in_at', 'last_seen_at', 'status', 'is_late', 'minutes_late', 'source', 'note', 'marked_by'])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by')->withDefault(['name' => 'Deleted user']);
    }

    /** Minutes between first and last activity that day (an activity span, not a timesheet). */
    public function activeMinutes(): int
    {
        return $this->check_in_at && $this->last_seen_at
            ? (int) max(0, $this->check_in_at->diffInMinutes($this->last_seen_at, true))
            : 0;
    }
}
