<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::SuperAdmin => 'bg-purple-100 text-purple-700',
            self::Admin => 'bg-sky-100 text-sky-700',
            self::Member => 'bg-slate-100 text-slate-700',
        };
    }
}
