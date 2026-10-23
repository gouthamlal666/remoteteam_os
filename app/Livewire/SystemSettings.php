<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\ActivityLogger;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WorkCalendar;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Organisation-wide settings. Super admins only. */
#[Layout('layouts.app')]
#[Title('System settings')]
class SystemSettings extends Component
{
    use WithFileUploads;

    public const DAYS = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];

    #[Url]
    public string $tab = 'general';

    // General
    public string $company_name = '';

    public string $default_timezone = '';

    public $logo;

    // Work
    public array $working_days = [];

    public array $saturday_offs = [];

    public int|string $late_grace_minutes = 15;

    public string $day_start = '';

    public string $day_end = '';

    public bool $report_reminder_enabled = false;

    public string $report_reminder_time = '';

    // Notifications & WhatsApp
    public bool $email_enabled = true;

    public bool $whatsapp_enabled = false;

    public string $whatsapp_driver = 'log';

    public string $default_country_code = '';

    public array $whatsapp_events = [];

    public string $meta_phone_number_id = '';

    public string $meta_token = '';

    public string $meta_template = '';

    public string $meta_language = '';

    public string $twilio_sid = '';

    public string $twilio_token = '';

    public string $twilio_from = '';

    public string $test_number = '';

    // Security
    public int $min_password_length = 8;

    public bool $member_direct_messages = true;

    /** Runs on every request (not just the first), so every action is protected. */
    public function boot(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->tab = in_array($this->tab, ['general', 'work', 'notifications', 'security'], true) ? $this->tab : 'general';
        $this->loadValues();
    }

    private function loadValues(): void
    {
        $s = Settings::all();
        $this->company_name = (string) $s['general.company_name'];
        $this->default_timezone = (string) $s['general.default_timezone'];
        $this->working_days = (array) $s['work.working_days'];
        $this->saturday_offs = array_map('strval', (array) $s['work.saturday_offs']);
        $this->late_grace_minutes = (int) $s['work.late_grace_minutes'];
        $this->day_start = (string) $s['work.day_start'];
        $this->day_end = (string) $s['work.day_end'];
        $this->report_reminder_enabled = (bool) $s['work.report_reminder_enabled'];
        $this->report_reminder_time = (string) $s['work.report_reminder_time'];
        $this->email_enabled = (bool) $s['notifications.email_enabled'];
        $this->whatsapp_enabled = (bool) $s['notifications.whatsapp_enabled'];
        $this->whatsapp_driver = (string) $s['whatsapp.driver'];
        $this->default_country_code = (string) $s['whatsapp.default_country_code'];
        $this->whatsapp_events = (array) $s['whatsapp.events'];
        $this->meta_phone_number_id = (string) $s['whatsapp.meta_phone_number_id'];
        $this->meta_template = (string) $s['whatsapp.meta_template'];
        $this->meta_language = (string) $s['whatsapp.meta_language'];
        $this->twilio_sid = (string) $s['whatsapp.twilio_sid'];
        $this->twilio_from = (string) $s['whatsapp.twilio_from'];
        $this->min_password_length = (int) $s['security.min_password_length'];
        $this->member_direct_messages = (bool) $s['security.member_direct_messages'];
        $this->meta_token = '';
        $this->twilio_token = '';
        $this->test_number = (string) auth()->user()->phone;
    }

    private function saved(string $section, ActivityLogger $activity): void
    {
        $activity->log(auth()->user(), 'settings.updated', "updated {$section} system settings");
        $this->dispatch('notify', message: ucfirst($section).' settings saved.');
    }

    public function saveGeneral(ActivityLogger $activity): void
    {
        $this->validate([
            'company_name' => ['required', 'string', 'max:60'],
            'default_timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        Settings::set(['general.company_name' => trim($this->company_name), 'general.default_timezone' => $this->default_timezone]);
        $this->saved('general', $activity);
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);

        $old = Settings::get('general.logo_path');
        Settings::set(['general.logo_path' => $this->logo->store('branding', 'local')]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        $this->logo = null;
        $this->dispatch('notify', message: 'Logo updated. Refresh to see it in the sidebar.');
    }

    public function removeLogo(): void
    {
        if ($path = Settings::get('general.logo_path')) {
            Storage::disk('local')->delete($path);
        }
        Settings::set(['general.logo_path' => null]);
    }

    public function saveWork(ActivityLogger $activity): void
    {
        $this->validate([
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [Rule::in(array_keys(self::DAYS))],
            'day_start' => ['required', 'date_format:H:i'],
            'day_end' => ['required', 'date_format:H:i'],
            'report_reminder_time' => ['required', 'date_format:H:i'],
            'saturday_offs' => ['array'],
            'saturday_offs.*' => [Rule::in(['1', '2', '3', '4', '5'])],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ], ['working_days.required' => 'Pick at least one working day.']);

        // Keep days in week order.
        $days = array_values(array_intersect(array_keys(self::DAYS), $this->working_days));

        Settings::set([
            'work.working_days' => $days,
            'work.day_start' => $this->day_start,
            'work.day_end' => $this->day_end,
            'work.report_reminder_enabled' => $this->report_reminder_enabled,
            'work.report_reminder_time' => $this->report_reminder_time,
            'work.saturday_offs' => collect($this->saturday_offs)->map(fn ($n) => (int) $n)->unique()->sort()->values()->all(),
            'work.late_grace_minutes' => (int) $this->late_grace_minutes,
        ]);
        app(WorkCalendar::class)->forgetCache();
        $this->saved('work', $activity);
    }

    public function saveNotifications(ActivityLogger $activity): void
    {
        $this->validate([
            'whatsapp_driver' => ['required', Rule::in(['log', 'meta', 'twilio'])],
            'default_country_code' => ['required', 'regex:/^\+?[1-9]\d{0,3}$/'],
            'whatsapp_events' => ['array'],
            'whatsapp_events.*' => [Rule::in(array_keys(Settings::WHATSAPP_EVENTS))],
            'meta_phone_number_id' => [Rule::requiredIf($this->whatsapp_enabled && $this->whatsapp_driver === 'meta'), 'nullable', 'regex:/^\d{5,30}$/'],
            'meta_token' => [Rule::requiredIf($this->whatsapp_enabled && $this->whatsapp_driver === 'meta' && ! Settings::hasSecret('whatsapp.meta_token')), 'nullable', 'string', 'max:1000'],
            'meta_template' => [Rule::requiredIf($this->whatsapp_driver === 'meta'), 'nullable', 'string', 'max:100'],
            'meta_language' => ['nullable', 'string', 'max:10'],
            'twilio_sid' => [Rule::requiredIf($this->whatsapp_enabled && $this->whatsapp_driver === 'twilio'), 'nullable', 'regex:/^AC[a-zA-Z0-9]{32}$/'],
            'twilio_token' => [Rule::requiredIf($this->whatsapp_enabled && $this->whatsapp_driver === 'twilio' && ! Settings::hasSecret('whatsapp.twilio_token')), 'nullable', 'string', 'max:200'],
            'twilio_from' => [Rule::requiredIf($this->whatsapp_enabled && $this->whatsapp_driver === 'twilio'), 'nullable', 'string', 'max:40'],
        ], [
            'default_country_code.regex' => 'Use a code like +91 or +44.',
            'meta_phone_number_id.regex' => 'The phone number ID is the numeric ID from Meta (digits only).',
            'twilio_sid.regex' => 'A Twilio Account SID starts with AC followed by 32 characters.',
        ]);

        Settings::set([
            'notifications.email_enabled' => $this->email_enabled,
            'notifications.whatsapp_enabled' => $this->whatsapp_enabled,
            'whatsapp.driver' => $this->whatsapp_driver,
            'whatsapp.default_country_code' => '+'.ltrim($this->default_country_code, '+'),
            'whatsapp.events' => array_values($this->whatsapp_events),
            'whatsapp.meta_phone_number_id' => $this->meta_phone_number_id ?: null,
            'whatsapp.meta_token' => $this->meta_token,
            'whatsapp.meta_template' => $this->meta_template ?: 'remoteteam_alert',
            'whatsapp.meta_language' => $this->meta_language ?: 'en',
            'whatsapp.twilio_sid' => $this->twilio_sid ?: null,
            'whatsapp.twilio_token' => $this->twilio_token,
            'whatsapp.twilio_from' => $this->twilio_from ?: null,
        ]);

        $this->meta_token = '';
        $this->twilio_token = '';
        $this->saved('notification', $activity);
    }

    public function forgetSecret(string $which): void
    {
        abort_unless(in_array($which, Settings::SECRETS, true), 422);
        Settings::forgetSecret($which);
        $this->dispatch('notify', message: 'Saved token removed.');
    }

    public function sendTest(WhatsAppSender $sender): void
    {
        $this->validate(['test_number' => ['required', 'string', 'max:30']]);

        $result = $sender->send($this->test_number, 'Test from '.Settings::companyName(),
            'WhatsApp notifications are working. 🎉', route('dashboard'), 'test', auth()->user());

        $this->dispatch('notify', message: match ($result->status) {
            'sent' => 'Test message sent to '.$result->to.'.',
            'logged' => 'Test mode: message written to the log (storage/logs/laravel.log).',
            default => 'Failed: '.$result->error,
        });
    }

    public function saveSecurity(ActivityLogger $activity): void
    {
        $this->validate(['min_password_length' => ['required', 'integer', 'min:8', 'max:64']]);

        Settings::set([
            'security.min_password_length' => $this->min_password_length,
            'security.member_direct_messages' => $this->member_direct_messages,
        ]);
        $this->saved('security', $activity);
    }

    public function render()
    {
        return view('livewire.system-settings', [
            'timezones' => collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => str_replace('_', ' ', $tz).' ('.now()->setTimezone($tz)->format('P').')']),
            'logoPath' => Settings::get('general.logo_path'),
            'hasMetaToken' => Settings::hasSecret('whatsapp.meta_token'),
            'hasTwilioToken' => Settings::hasSecret('whatsapp.twilio_token'),
            'deliveries' => $this->tab === 'notifications' ? WhatsappMessage::with('user:id,name')->latest()->limit(15)->get() : collect(),
            'optedIn' => User::active()->where('whatsapp_notifications', true)->count(),
        ]);
    }
}
