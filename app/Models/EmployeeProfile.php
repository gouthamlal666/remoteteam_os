<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'employee_code', 'employment_type', 'date_of_joining', 'date_of_birth', 'gender', 'blood_group', 'alternate_phone', 'personal_email', 'address_line', 'city', 'state', 'country', 'postal_code', 'guardian_name', 'guardian_relation', 'guardian_phone', 'notes'])]
class EmployeeProfile extends Model
{
    public const EMPLOYMENT_TYPES = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern', 'freelance' => 'Freelance'];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not' => 'Prefer not to say'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const RELATIONS = ['Father', 'Mother', 'Spouse', 'Brother', 'Sister', 'Guardian', 'Friend', 'Other'];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullAddress(): ?string
    {
        $parts = array_filter([$this->address_line, $this->city, $this->state, $this->postal_code, $this->country]);

        return $parts ? implode(', ', $parts) : null;
    }
}
