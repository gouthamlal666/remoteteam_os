<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Revision = 'revision';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Review => 'Review',
            self::Revision => 'Revision',
            self::Completed => 'Completed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Todo => 'bg-slate-100 text-slate-700',
            self::InProgress => 'bg-blue-100 text-blue-700',
            self::Review => 'bg-amber-100 text-amber-800',
            self::Revision => 'bg-orange-100 text-orange-700',
            self::Completed => 'bg-emerald-100 text-emerald-700',
        };
    }

    /** Statuses a member may move their own task into directly. */
    public static function memberSettable(): array
    {
        return [self::Todo, self::InProgress];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
