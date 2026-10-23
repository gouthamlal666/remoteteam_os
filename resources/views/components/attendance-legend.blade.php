<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500']) }}>
    @foreach ([['P', 'Present', 'present'], ['L', 'Late', 'late'], ['½', 'Half day', 'half'], ['LV', 'Leave', 'leave'], ['A', 'Absent', 'absent'], ['H', 'Holiday', 'holiday'], ['W', 'Weekly off', 'weekly_off']] as [$code, $label, $tone])
        <span class="flex items-center gap-1.5"><x-attendance-cell :tone="$tone" :code="$code" class="size-5 text-[10px]" /> {{ $label }}</span>
    @endforeach
</div>
