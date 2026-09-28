<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-lg font-semibold text-slate-900">Sign in to your workspace</h1>
    <p class="mt-1 text-sm text-slate-500">Accounts are created by your administrator.</p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="mt-6 space-y-4">
        <x-field label="Email" for="email" error="email">
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus class="input">
        </x-field>
        <x-field label="Password" for="password" error="password">
            <input wire:model="password" id="password" type="password" autocomplete="current-password" class="input">
        </x-field>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-indigo-600"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">Forgot password?</a>
        </div>
        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>

</div>
