<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-lg font-semibold text-slate-900">Choose a new password</h1>
    <form wire:submit="resetPassword" class="mt-6 space-y-4">
        <x-field label="Email" for="email" error="email">
            <input wire:model="email" id="email" type="email" autocomplete="username" class="input">
        </x-field>
        <x-field label="New password" for="password" error="password">
            <input wire:model="password" id="password" type="password" autocomplete="new-password" class="input">
        </x-field>
        <x-field label="Confirm password" for="password_confirmation">
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="input">
        </x-field>
        <button type="submit" class="btn-primary w-full">Reset password</button>
    </form>
</div>
