@props(['color' => 'bg-slate-100 text-slate-700'])
<span {{ $attributes->merge(['class' => "$color inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"]) }}>{{ $slot }}</span>
