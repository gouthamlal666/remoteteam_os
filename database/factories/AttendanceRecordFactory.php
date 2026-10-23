<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today()->toDateString(),
            'check_in_at' => now(),
            'last_seen_at' => now(),
            'status' => 'present',
            'is_late' => false,
            'minutes_late' => 0,
            'source' => 'auto',
        ];
    }
}
