<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-lg font-semibold text-slate-900">Forgot your password?</h1>
    @if ($sent)
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">If an active account exists for {{ $email }}, a reset link is on its way. Check your inbox.</p>
    @else
        <p class="mt-1 text-sm text-slate-500">Enter your work email and we'll send you a reset link.</p>
        <form wire:submit="sendLink" class="mt-6 space-y-4">
            <x-field label="Email" for="email" error="email">
                <input wire:model="email" id="email" type="email" autocomplete="username" autofocus class="input">
            </x-field>
            <button type="submit" class="btn-primary w-full">Email reset link</button>
        </form>
    @endif
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Back to sign in</a></p>
</div>
