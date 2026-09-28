<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">A transparent log of what happened on projects and tasks you can see.</p>
        <select wire:model.live="project" class="input w-auto">
            <option value="">All projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
    </div>

    @forelse ($groups as $date => $items)
        <x-card :title="\Illuminate\Support\Carbon::parse($date)->isToday() ? 'Today' : (\Illuminate\Support\Carbon::parse($date)->isYesterday() ? 'Yesterday' : \Illuminate\Support\Carbon::parse($date)->format('l, d M Y'))">
            <x-activity-feed :activities="$items" />
        </x-card>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 bg-white"><x-empty>No activity yet.</x-empty></div>
    @endforelse

    {{ $activities->links() }}
</div>
