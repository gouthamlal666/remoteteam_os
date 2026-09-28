<?php

namespace App\Support;

/**
 * Maps stored colour names to full Tailwind class strings so the classes
 * are present verbatim in source and survive Tailwind's content scan.
 */
class Palette
{
    private const DOT = [
        'indigo' => 'bg-indigo-500',
        'sky' => 'bg-sky-500',
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
        'violet' => 'bg-violet-500',
        'slate' => 'bg-slate-500',
    ];

    private const BAR = [
        'indigo' => 'border-t-indigo-500',
        'sky' => 'border-t-sky-500',
        'emerald' => 'border-t-emerald-500',
        'amber' => 'border-t-amber-500',
        'rose' => 'border-t-rose-500',
        'violet' => 'border-t-violet-500',
        'slate' => 'border-t-slate-500',
    ];

    public static function dot(?string $color): string
    {
        return self::DOT[$color] ?? self::DOT['slate'];
    }

    public static function bar(?string $color): string
    {
        return self::BAR[$color] ?? self::BAR['slate'];
    }
}
