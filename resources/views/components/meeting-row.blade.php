@props(['meeting', 'user'])
@php
    [$label, $badge] = $meeting->displayStatus();
    $mine = $meeting->participants->firstWhere('id', $user->id)?->pivot;
@endphp
<a {{ $attributes->merge(['class' => 'flex items-center gap-4 px-4 py-3 hover:bg-slate-50']) }} href="{{ route('meetings.show', $meeting) }}" wire:navigate>
    <div class="w-14 shrink-0 rounded-lg border border-slate-200 text-center">
        <p class="rounded-t-lg bg-indigo-600 text-[10px] font-semibold text-white uppercase">{{ $meeting->starts_at->format('M') }}</p>
        <p class="py-0.5 text-lg font-semibold text-slate-900">{{ $meeting->starts_at->format('d') }}</p>
    </div>
    <div class="min-w-0 flex-1">
        <p class="truncate font-medium text-slate-900">{{ $meeting->title }}</p>
        <p class="truncate text-xs text-slate-500">
            {{ $meeting->starts_at->format('D, h:i A') }} – {{ $meeting->endsAt()->format('h:i A') }}
            · {{ $meeting->participants->count() }} people
            @if ($meeting->project) · {{ $meeting->project->name }} @endif
        </p>
    </div>
    @if ($mine && $mine->response === 'pending' && $meeting->isUpcoming())
        <x-badge color="bg-amber-100 text-amber-800">Respond</x-badge>
    @endif
    <x-badge :color="$badge">{{ $label }}</x-badge>
</a>
