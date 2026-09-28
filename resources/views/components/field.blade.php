@props(['label', 'for' => null, 'error' => null])
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    {{ $slot }}
    @if ($error)
        @error($error) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
</div>
