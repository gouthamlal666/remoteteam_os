{{-- Company logo + name from System settings (falls back to the RT mark). --}}
@props(['size' => 'sm'])
@php
    $name = \App\Support\Settings::companyName();
    $logo = \App\Support\Settings::get('general.logo_path');
    $initials = $name === 'RemoteTeam OS'
        ? 'RT'
        : (collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'RT');
    $box = $size === 'lg' ? 'size-10 rounded-xl' : 'size-8 rounded-lg text-sm';
@endphp
<span {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    @if ($logo)
        <img src="{{ route('branding.logo', ['v' => substr(md5($logo), 0, 8)]) }}" alt="" class="{{ $box }} object-contain">
    @else
        <span class="{{ $box }} flex items-center justify-center bg-indigo-600 font-bold text-white">{{ $initials }}</span>
    @endif
    <span @class(['font-semibold text-slate-900', 'text-xl' => $size === 'lg'])>{{ $name }}</span>
</span>
