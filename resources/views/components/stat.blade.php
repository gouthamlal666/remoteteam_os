@props(['label', 'value', 'tone' => 'text-slate-900', 'hint' => null])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4']) }}>
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
</div>
