@props(['value', 'color' => 'bg-indigo-500'])
<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-slate-100']) }} role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
    <div class="h-full rounded-full {{ $color }} transition-all" style="width: {{ max(0, min(100, $value)) }}%"></div>
</div>
