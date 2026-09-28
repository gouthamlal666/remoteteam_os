@props(['title' => null, 'actions' => null])
<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white']) }}>
    @if ($title || $actions)
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">{{ $title }}</h2>
            {{ $actions }}
        </header>
    @endif
    {{ $slot }}
</section>
