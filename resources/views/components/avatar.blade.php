@props(['user', 'size' => 'size-8', 'presence' => false])
@php $photo = $user->avatarUrl(); @endphp
<span {{ $attributes->merge(['class' => "$size relative inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 ring-2 ring-white"]) }}
      title="{{ $user->name }}{{ $presence && $user->isOnline() ? ' · online' : '' }}">
    @if ($photo)
        {{-- Falls back to initials if the photo file is missing. --}}
        <span x-data="{ failed: false }" class="contents">
            <img x-show="! failed" x-on:error="failed = true" src="{{ $photo }}" alt="" class="size-full rounded-full object-cover" loading="lazy">
            <span x-show="failed" x-cloak>{{ $user->initials() }}</span>
        </span>
    @else
        {{ $user->initials() }}
    @endif
    @if ($presence)
        <span @class(['absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full ring-2 ring-white', 'bg-emerald-500' => $user->isOnline(), 'bg-slate-300' => ! $user->isOnline()])></span>
    @endif
</span>
