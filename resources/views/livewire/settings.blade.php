<div class="mx-auto max-w-3xl space-y-6">
    {{-- Photo --}}
    <x-card title="Profile photo">
        <div class="flex items-center gap-4 p-4">
            <x-avatar :user="auth()->user()" size="size-16 text-lg" />
            <div class="space-y-1 text-sm">
                <label class="cursor-pointer font-medium text-indigo-600 hover:underline">
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    <span wire:loading.remove wire:target="photo">{{ auth()->user()->avatar_path ? 'Change photo' : 'Upload photo' }}</span>
                    <span wire:loading wire:target="photo">Uploading…</span>
                </label>
                @if (auth()->user()->avatar_path)
                    <span class="text-slate-300">·</span>
                    <button wire:click="removePhoto" class="text-slate-500 hover:text-red-600">Remove</button>
                @endif
                <p class="text-xs text-slate-400">JPG, PNG or WebP, max 3 MB. Your teammates see it next to your name.</p>
                @error('photo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-card>

    {{-- Status --}}
    <x-card title="Status">
        <form wire:submit="saveStatus" class="space-y-4 p-4">
            <p class="text-sm text-slate-500">Let your team know what you're up to. It shows next to your name across the app.</p>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Livewire\Settings::STATUS_PRESETS as $i => [$emoji, $text])
                    <button type="button" wire:click="usePreset({{ $i }})" class="rounded-full border border-slate-200 px-3 py-1 text-sm hover:border-indigo-300">{{ $emoji }} {{ $text }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-[5rem_1fr_11rem]">
                <input wire:model="status_emoji" class="input text-center" placeholder="🙂" maxlength="4" aria-label="Status emoji">
                <input wire:model="status_text" class="input" placeholder="What's your status?" aria-label="Status text">
                <select wire:model="status_clear" class="input" aria-label="Clear after">
                    <option value="">Don't clear</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                    <option value="120">2 hours</option>
                    <option value="240">4 hours</option>
                    <option value="today">Today</option>
                    <option value="week">This week</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" wire:click="clearStatus" class="btn-secondary">Clear</button>
                <button type="submit" class="btn-primary">Set status</button>
            </div>
        </form>
    </x-card>

    {{-- Profile & remote working --}}
    <x-card title="Profile & working hours">
        <form wire:submit="saveProfile" class="grid gap-4 p-4 sm:grid-cols-2">
            <x-field label="Full name" for="s-name" error="name">
                <input wire:model="name" id="s-name" class="input">
            </x-field>
            <x-field label="Job title" for="s-title" error="job_title">
                <input wire:model="job_title" id="s-title" class="input">
            </x-field>
            <x-field label="Mobile number" for="s-phone" error="phone">
                <input wire:model="phone" id="s-phone" class="input">
            </x-field>
            <x-field label="Skills (comma separated)" for="s-skills" error="skills">
                <input wire:model="skills" id="s-skills" class="input">
            </x-field>
            <x-field label="About" for="s-bio" error="bio" class="sm:col-span-2">
                <textarea wire:model="bio" id="s-bio" rows="2" class="input"></textarea>
            </x-field>
            <x-field label="Timezone" for="s-tz" error="timezone" class="sm:col-span-2">
                <select wire:model="timezone" id="s-tz" class="input">
                    @foreach ($timezones as $tz => $label)
                        <option value="{{ $tz }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500">Your local time now: {{ $localTime->format('h:i A, D d M') }}. Teammates see your local time on your profile and in chat.</p>
            </x-field>
            <x-field label="Working hours start" for="s-ws" error="work_start">
                <input wire:model="work_start" id="s-ws" type="time" class="input">
            </x-field>
            <x-field label="Working hours end" for="s-we" error="work_end">
                <input wire:model="work_end" id="s-we" type="time" class="input">
            </x-field>
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 sm:col-span-2">
                <input wire:model="email_notifications" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                <span class="text-sm"><span class="block font-medium text-slate-800">Email notifications</span><span class="text-slate-500">Task assignments, review results, due-date and meeting reminders. In-app notifications are always on.</span></span>
            </label>
            @if (\App\Support\Settings::get('notifications.whatsapp_enabled'))
                <label class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 sm:col-span-2">
                    <input wire:model="whatsapp_notifications" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
                    <span class="text-sm"><span class="block font-medium text-slate-800">WhatsApp notifications</span><span class="text-slate-500">Get important alerts on WhatsApp at your mobile number{{ $phone ? ' ('.$phone.')' : '' }}.</span></span>
                </label>
            @endif
            <div class="flex justify-end sm:col-span-2"><button type="submit" class="btn-primary">Save profile</button></div>
        </form>
    </x-card>

    {{-- Password --}}
    <x-card title="Change password">
        <form wire:submit="changePassword" class="grid gap-4 p-4 sm:grid-cols-3">
            <x-field label="Current password" for="s-cur" error="current_password">
                <input wire:model="current_password" id="s-cur" type="password" autocomplete="current-password" class="input">
            </x-field>
            <x-field label="New password" for="s-new" error="password">
                <input wire:model="password" id="s-new" type="password" autocomplete="new-password" class="input">
            </x-field>
            <x-field label="Confirm new password" for="s-conf">
                <input wire:model="password_confirmation" id="s-conf" type="password" autocomplete="new-password" class="input">
            </x-field>
            <div class="flex justify-end sm:col-span-3"><button type="submit" class="btn-primary">Update password</button></div>
        </form>
    </x-card>
</div>
