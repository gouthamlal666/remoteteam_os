{{-- Client logo, or coloured initials when there's no logo (or it fails to load). --}}
@props(['client', 'size' => 'size-10 text-sm'])
@php $url = $client?->logoUrl(); @endphp
<span {{ $attributes->merge(['class' => "$size flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 font-bold text-white ring-1 ring-black/5"]) }}
      @if ($url) x-data="{ failed: false }" :class="failed ? '' : 'bg-white bg-none'" @endif>
    @if ($url)
        <img x-show="! failed" x-on:error="failed = true" src="{{ $url }}" alt="" class="size-full object-contain p-0.5" loading="lazy">
        <span x-show="failed" x-cloak>{{ $client->initials() }}</span>
    @else
        {{ $client?->initials() ?? 'IN' }}
    @endif
</span>
