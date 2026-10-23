@props(['tone', 'code' => '', 'title' => null])
@php
    $tones = [
        'present' => 'bg-emerald-100 text-emerald-800',
        'late' => 'bg-amber-100 text-amber-800',
        'half' => 'bg-teal-100 text-teal-800',
        'leave' => 'bg-sky-100 text-sky-800',
        'absent' => 'bg-red-100 text-red-700',
        'holiday' => 'bg-violet-100 text-violet-700',
        'weekly_off' => 'bg-slate-100 text-slate-400',
        'extra' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300',
        'pending' => 'bg-white text-slate-300 ring-1 ring-slate-200',
        'future' => 'bg-white text-slate-300',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded font-semibold '.($tones[$tone] ?? $tones['future'])]) }} @if ($title) title="{{ $title }}" @endif>{{ $code }}</span>
