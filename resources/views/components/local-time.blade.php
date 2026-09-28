{{-- "3:42 PM · working hours" style label for a teammate's local time. --}}
@props(['user'])
@php $working = $user->isWorkingNow(); @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }} title="{{ $user->timezone }}">
    <x-icon name="clock" class="size-3.5 shrink-0 text-slate-400" /> {{ $user->localTime()->format('h:i A') }}
    @if ($working === true)
        <span class="text-emerald-600">· working</span>
    @elseif ($working === false)
        <span class="text-slate-400">· off hours</span>
    @endif
</span>
