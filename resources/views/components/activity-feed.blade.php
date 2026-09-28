@props(['activities'])
<ol class="divide-y divide-slate-100">
    @forelse ($activities as $activity)
        <li class="flex gap-3 px-4 py-3">
            @if ($activity->user)
                <x-avatar :user="$activity->user" size="size-7" />
            @endif
            <div class="min-w-0 flex-1 text-sm">
                <p class="text-slate-700">
                    <span class="font-medium text-slate-900">{{ $activity->user?->name ?? 'System' }}</span>
                    @if ($activity->subject_type === (new \App\Models\Task)->getMorphClass() && $activity->subject)
                        {{ \Illuminate\Support\Str::before($activity->description, '"') }}<a href="{{ route('tasks.show', $activity->subject_id) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">{{ \Illuminate\Support\Str::between($activity->description, '"', '"') }}</a>{{ \Illuminate\Support\Str::afterLast($activity->description, '"') }}
                    @else
                        {{ $activity->description }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-slate-400">
                    <time datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('d M Y, h:i A') }}">{{ $activity->created_at->diffForHumans() }}</time>
                    @if ($activity->project) · {{ $activity->project->name }} @endif
                </p>
            </div>
        </li>
    @empty
        <li><x-empty>No activity yet.</x-empty></li>
    @endforelse
</ol>
