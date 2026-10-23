@php
    $tabs = [
        'general' => ['General', 'building'],
        'work' => ['Work & reports', 'briefcase'],
        'notifications' => ['Notifications & WhatsApp', 'chat'],
        'security' => ['Security', 'shield'],
    ];
@endphp
<div class="mx-auto max-w-5xl">
    <p class="mb-5 text-sm text-slate-500">Organisation-wide settings. Only super admins can see this page. Personal preferences are in <a href="{{ route('settings') }}" wire:navigate class="text-indigo-600 hover:underline">My settings</a>.</p>

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        {{-- Tabs --}}
        <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Settings sections">
            @foreach ($tabs as $key => [$label, $icon])
                <button wire:click="$set('tab', '{{ $key }}')" @class([
                    'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium whitespace-nowrap',
                    'bg-white text-indigo-700 shadow-xs ring-1 ring-slate-200' => $tab === $key,
                    'text-slate-600 hover:bg-white/70' => $tab !== $key,
                ])>
                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="min-w-0 space-y-6">
            {{-- ───────── General ───────── --}}
            @if ($tab === 'general')
                <x-card title="Company">
                    <form wire:submit="saveGeneral" class="space-y-5 p-5">
                        <div class="flex items-center gap-4">
                            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200">
                                @if ($logoPath)
                                    <img src="{{ route('branding.logo', ['v' => substr(md5($logoPath), 0, 8)]) }}" alt="Company logo" class="size-full object-contain">
                                @else
                                    <x-brand class="[&>span:last-child]:hidden" size="lg" />
                                @endif
                            </div>
                            <div class="text-sm">
                                <label class="btn-secondary cursor-pointer py-1.5">
                                    <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="sr-only">
                                    <span wire:loading.remove wire:target="logo">{{ $logoPath ? 'Change logo' : 'Upload logo' }}</span>
                                    <span wire:loading wire:target="logo">Uploading…</span>
                                </label>
                                @if ($logoPath)
                                    <button type="button" wire:click="removeLogo" class="ml-2 text-slate-500 hover:text-red-600">Remove</button>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">Square PNG, JPG or WebP, max 2 MB. Shown in the sidebar and on the sign-in page.</p>
                                @error('logo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <x-field label="Company name" for="ss-name" error="company_name">
                            <input wire:model="company_name" id="ss-name" class="input" maxlength="60">
                            <p class="text-xs text-slate-400">Replaces “RemoteTeam OS” in the sidebar, sign-in page, browser tab and emails.</p>
                        </x-field>
                        <x-field label="Default timezone for new people" for="ss-tz" error="default_timezone">
                            <select wire:model="default_timezone" id="ss-tz" class="input">
                                @foreach ($timezones as $tz => $label)
                                    <option value="{{ $tz }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400">Each person can change their own timezone in My settings.</p>
                        </x-field>
                        <div class="flex justify-end"><button type="submit" class="btn-primary">Save</button></div>
                    </form>
                </x-card>
            @endif

            {{-- ───────── Work ───────── --}}
            @if ($tab === 'work')
                <x-card title="Working week">
                    <form wire:submit="saveWork" class="space-y-5 p-5">
                        <x-field label="Working days" error="working_days">
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Livewire\SystemSettings::DAYS as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" wire:model.live="working_days" value="{{ $value }}" class="peer sr-only">
                                        <span class="block rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400">Used for attendance, leave day counting, the weekly report and daily reminders.</p>
                        </x-field>
                        @if (in_array('sat', $working_days, true))
                            <x-field label="Saturdays off each month" error="saturday_offs">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ([1, 2, 3, 4, 5] as $n)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" wire:model="saturday_offs" value="{{ $n }}" class="peer sr-only">
                                            <span class="block rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-medium text-slate-600 peer-checked:border-violet-600 peer-checked:bg-violet-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-violet-300">{{ \App\Services\WorkCalendar::ordinal($n) }} Sat</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-xs text-slate-400">These Saturdays are holidays for everyone, e.g. the 2nd Saturday as in Kerala government offices.</p>
                            </x-field>
                        @endif
                        <x-field label="Late after (minutes past start of day)" for="ss-grace" error="late_grace_minutes" class="max-w-xs">
                            <input wire:model="late_grace_minutes" id="ss-grace" type="number" min="0" max="240" class="input">
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Default start of day" for="ss-start" error="day_start">
                                <input wire:model="day_start" id="ss-start" type="time" class="input">
                            </x-field>
                            <x-field label="Default end of day" for="ss-end" error="day_end">
                                <input wire:model="day_end" id="ss-end" type="time" class="input">
                            </x-field>
                        </div>
                        <p class="-mt-2 text-xs text-slate-400">New people start with these working hours; they can adjust their own.</p>

                        <div class="rounded-xl border border-slate-200 p-4">
                            <label class="flex items-start gap-3">
                                <input wire:model.live="report_reminder_enabled" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                                <span class="text-sm"><span class="block font-medium text-slate-800">Daily report reminder</span><span class="text-slate-500">On working days, remind everyone who hasn't submitted their daily report (in-app, email and WhatsApp if enabled).</span></span>
                            </label>
                            <div @class(['mt-3 ml-7 max-w-40', 'opacity-50' => ! $report_reminder_enabled])>
                                <x-field label="Send at" for="ss-rtime" error="report_reminder_time">
                                    <input wire:model="report_reminder_time" id="ss-rtime" type="time" class="input" @disabled(! $report_reminder_enabled)>
                                </x-field>
                            </div>
                        </div>
                        <div class="flex justify-end"><button type="submit" class="btn-primary">Save</button></div>
                    </form>
                </x-card>
            @endif

            {{-- ───────── Notifications & WhatsApp ───────── --}}
            @if ($tab === 'notifications')
                <form wire:submit="saveNotifications" class="space-y-6">
                    <x-card title="Channels">
                        <div class="divide-y divide-slate-100">
                            <label class="flex items-start gap-3 p-5">
                                <input wire:model="email_enabled" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                                <span class="text-sm"><span class="block font-medium text-slate-800">Email notifications</span><span class="text-slate-500">Important alerts by email. People can still opt out individually.
                                    @if (config('mail.default') === 'log') <span class="text-amber-700">Mail is in log mode: emails are written to the log until MAIL_* is configured in .env.</span> @endif</span></span>
                            </label>
                            <label class="flex items-start gap-3 p-5">
                                <input wire:model.live="whatsapp_enabled" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
                                <span class="text-sm"><span class="block font-medium text-slate-800">WhatsApp notifications</span><span class="text-slate-500">People opt in from My settings with their mobile number. {{ $optedIn }} {{ str('person')->plural($optedIn) }} opted in so far.</span></span>
                            </label>
                        </div>
                    </x-card>

                    <x-card title="WhatsApp provider">
                        <div class="space-y-5 p-5">
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach (['log' => ['Test mode', 'No account needed. Messages are written to the log so you can try everything.'], 'meta' => ['Meta WhatsApp Cloud API', 'Official API from Meta. Needs a Business account and an approved template.'], 'twilio' => ['Twilio', 'Easy to start with the Twilio sandbox. Paid per message.']] as $value => [$label, $hint])
                                    <label @class(['cursor-pointer rounded-xl border p-3 transition', 'border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500' => $whatsapp_driver === $value, 'border-slate-200 hover:border-slate-300' => $whatsapp_driver !== $value])>
                                        <input type="radio" wire:model.live="whatsapp_driver" value="{{ $value }}" class="sr-only">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $label }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @if ($whatsapp_driver === 'meta')
                                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                                    <x-field label="Phone number ID" for="ss-mid" error="meta_phone_number_id">
                                        <input wire:model="meta_phone_number_id" id="ss-mid" class="input font-mono" placeholder="123456789012345">
                                    </x-field>
                                    <x-field label="Permanent access token" for="ss-mtok" error="meta_token">
                                        <input wire:model="meta_token" id="ss-mtok" type="password" autocomplete="off" class="input font-mono" placeholder="{{ $hasMetaToken ? '•••••••• saved (leave blank to keep)' : 'EAAG…' }}">
                                        @if ($hasMetaToken) <button type="button" wire:click="forgetSecret('whatsapp.meta_token')" wire:confirm="Remove the saved Meta token?" class="text-xs text-slate-500 hover:text-red-600">Remove saved token</button> @endif
                                    </x-field>
                                    <x-field label="Template name" for="ss-mtpl" error="meta_template">
                                        <input wire:model="meta_template" id="ss-mtpl" class="input font-mono">
                                    </x-field>
                                    <x-field label="Template language" for="ss-mlang" error="meta_language">
                                        <input wire:model="meta_language" id="ss-mlang" class="input font-mono" placeholder="en">
                                    </x-field>
                                    <div class="rounded-lg bg-white p-3 text-xs text-slate-600 ring-1 ring-slate-200 sm:col-span-2">
                                        <p class="font-medium text-slate-800">Create this template in Meta Business Manager (category: Utility)</p>
                                        <pre class="mt-2 font-mono whitespace-pre-wrap text-slate-700">🔔 @{{1}}
@{{2}}
Open: @{{3}}</pre>
                                        <p class="mt-2">WhatsApp only lets businesses start conversations with approved templates. Values filled in: 1 = alert title, 2 = message, 3 = link.</p>
                                    </div>
                                </div>
                            @elseif ($whatsapp_driver === 'twilio')
                                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                                    <x-field label="Account SID" for="ss-tsid" error="twilio_sid">
                                        <input wire:model="twilio_sid" id="ss-tsid" class="input font-mono" placeholder="AC…">
                                    </x-field>
                                    <x-field label="Auth token" for="ss-ttok" error="twilio_token">
                                        <input wire:model="twilio_token" id="ss-ttok" type="password" autocomplete="off" class="input font-mono" placeholder="{{ $hasTwilioToken ? '•••••••• saved (leave blank to keep)' : '' }}">
                                        @if ($hasTwilioToken) <button type="button" wire:click="forgetSecret('whatsapp.twilio_token')" wire:confirm="Remove the saved Twilio token?" class="text-xs text-slate-500 hover:text-red-600">Remove saved token</button> @endif
                                    </x-field>
                                    <x-field label="WhatsApp sender number" for="ss-tfrom" error="twilio_from" class="sm:col-span-2">
                                        <input wire:model="twilio_from" id="ss-tfrom" class="input font-mono" placeholder="+14155238886">
                                        <p class="text-xs text-slate-400">Sandbox: +14155238886 (each recipient must first send the join code to it). Production numbers need approved templates.</p>
                                    </x-field>
                                </div>
                            @else
                                <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Test mode: nothing is sent to phones. Messages appear in <code class="rounded bg-white px-1">storage/logs/laravel.log</code> and in the delivery log below, so you can check everything before connecting a provider.</p>
                            @endif

                            <x-field label="Default country code" for="ss-cc" error="default_country_code" class="max-w-48">
                                <input wire:model="default_country_code" id="ss-cc" class="input font-mono" placeholder="+91">
                                <p class="text-xs text-slate-400">Added to numbers saved without one.</p>
                            </x-field>
                        </div>
                    </x-card>

                    <x-card title="Which alerts go to WhatsApp">
                        <div class="grid gap-2 p-5 sm:grid-cols-2">
                            @foreach (\App\Support\Settings::WHATSAPP_EVENTS as $value => $label)
                                <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                                    <input type="checkbox" wire:model="whatsapp_events" value="{{ $value }}" class="rounded border-slate-300 text-emerald-600">
                                    <span class="text-slate-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-400">Chat alerts can be noisy on WhatsApp, so they're off by default. Everything always appears in the in-app bell.</p>
                    </x-card>

                    <div class="flex justify-end"><button type="submit" class="btn-primary">Save notification settings</button></div>
                </form>

                <x-card title="Send a test message">
                    <form wire:submit="sendTest" class="flex flex-wrap items-end gap-3 p-5">
                        <x-field label="WhatsApp number" for="ss-test" error="test_number" class="min-w-56 flex-1">
                            <input wire:model="test_number" id="ss-test" type="tel" class="input font-mono" placeholder="+91 98765 43210">
                        </x-field>
                        <button type="submit" class="btn-success" wire:loading.attr="disabled" wire:target="sendTest">
                            <span wire:loading.remove wire:target="sendTest">Send test</span><span wire:loading wire:target="sendTest">Sending…</span>
                        </button>
                        <p class="w-full text-xs text-slate-400">Uses the saved provider settings, so save first if you changed them.</p>
                    </form>
                </x-card>

                <x-card title="Recent WhatsApp deliveries">
                    <ul class="divide-y divide-slate-100">
                        @forelse ($deliveries as $d)
                            <li wire:key="wa-{{ $d->id }}" class="flex items-start gap-3 px-5 py-3 text-sm">
                                <x-badge :color="['sent' => 'bg-emerald-100 text-emerald-700', 'logged' => 'bg-slate-100 text-slate-600', 'failed' => 'bg-red-100 text-red-700'][$d->status] ?? 'bg-slate-100 text-slate-600'">{{ $d->status === 'logged' ? 'Test mode' : ucfirst($d->status) }}</x-badge>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-slate-800"><span class="font-medium">{{ $d->user->name }}</span> · <span class="font-mono text-xs">{{ $d->to }}</span> · {{ \App\Support\Settings::WHATSAPP_EVENTS[$d->type] ?? $d->type }}</p>
                                    @if ($d->error) <p class="text-xs text-red-600">{{ $d->error }}</p> @endif
                                </div>
                                <span class="shrink-0 text-xs text-slate-400">{{ $d->created_at->diffForHumans() }}</span>
                            </li>
                        @empty
                            <li><x-empty class="py-6">No WhatsApp messages yet.</x-empty></li>
                        @endforelse
                    </ul>
                </x-card>
            @endif

            {{-- ───────── Security ───────── --}}
            @if ($tab === 'security')
                <x-card title="Security">
                    <form wire:submit="saveSecurity" class="space-y-5 p-5">
                        <x-field label="Minimum password length" for="ss-minpw" error="min_password_length" class="max-w-48">
                            <input wire:model="min_password_length" id="ss-minpw" type="number" min="8" max="64" class="input">
                            <p class="text-xs text-slate-400">Applies to new and changed passwords. Existing passwords keep working.</p>
                        </x-field>
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                            <input wire:model="member_direct_messages" type="checkbox" class="mt-0.5 rounded border-slate-300 text-indigo-600">
                            <span class="text-sm"><span class="block font-medium text-slate-800">Members can message each other privately</span><span class="text-slate-500">When off, members can only start private chats with admins and super admins. Existing conversations are kept.</span></span>
                        </label>
                        <div class="flex justify-end"><button type="submit" class="btn-primary">Save</button></div>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</div>
