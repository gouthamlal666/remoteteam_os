<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'report_date', 'completed', 'in_progress', 'blockers', 'hours_worked'])]
class DailyReport extends Model
{
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'hours_worked' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
