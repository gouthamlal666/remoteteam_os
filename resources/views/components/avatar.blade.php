@props(['user', 'size' => 'size-8', 'presence' => false])
@php $photo = $user->avatarUrl(); @endphp
<span {{ $attributes->merge(['class' => "$size relative inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white"]) }}
      title="{{ $user->name }}{{ $presence && $user->isOnline() ? ' · online' : '' }}">
    @if ($photo)
        <img src="{{ $photo }}" alt="" class="size-full rounded-full object-cover" loading="lazy">
    @else
        {{ $user->initials() }}
    @endif
    @if ($presence)
        <span @class(['absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full ring-2 ring-white', 'bg-emerald-500' => $user->isOnline(), 'bg-slate-300' => ! $user->isOnline()])></span>
    @endif
</span>
