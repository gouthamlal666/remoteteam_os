<?php

namespace App\Livewire;

use App\Services\ActivityLogger;
use App\Services\EmployeeService;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use WithFileUploads;

    public $photo;

    public const STATUS_PRESETS = [
        ['📅', 'In a meeting', 60],
        ['🎯', 'Focusing', 120],
        ['🍽️', 'Lunch break', 60],
        ['🤒', 'Out sick', null],
        ['🌴', 'On leave', null],
        ['🏠', 'Working remotely', null],
    ];

    // Profile
    public string $name = '';

    public string $phone = '';

    public string $job_title = '';

    public string $skills = '';

    public string $bio = '';

    // Remote working
    public string $timezone = '';

    public string $work_start = '';

    public string $work_end = '';

    // Status
    public string $status_emoji = '';

    public string $status_text = '';

    /** Minutes until the status clears; '' = never. */
    public string $status_clear = '';

    // Notifications
    public bool $email_notifications = true;

    public bool $whatsapp_notifications = false;

    // Password
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;
        $this->job_title = (string) $user->job_title;
        $this->skills = implode(', ', $user->skills ?? []);
        $this->bio = (string) $user->bio;
        $this->timezone = $user->timezone ?: config('app.timezone');
        $this->work_start = $user->work_start ? substr($user->work_start, 0, 5) : '';
        $this->work_end = $user->work_end ? substr($user->work_end, 0, 5) : '';
        $this->status_emoji = $user->activeStatus() ? (string) $user->status_emoji : '';
        $this->status_text = $user->activeStatus() ? (string) $user->status_text : '';
        $this->email_notifications = $user->email_notifications ?? true;
        $this->whatsapp_notifications = (bool) $user->whatsapp_notifications;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'skills' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'work_start' => ['nullable', 'date_format:H:i', 'required_with:work_end'],
            'work_end' => ['nullable', 'date_format:H:i', 'required_with:work_start'],
            'email_notifications' => ['boolean'],
            'whatsapp_notifications' => ['boolean'],
        ]);

        if ($data['whatsapp_notifications'] && ! WhatsAppSender::normalise($data['phone'])) {
            $this->addError('phone', 'Add a valid mobile number to get WhatsApp notifications.');

            return;
        }

        $data['skills'] = Str::of($data['skills'] ?? '')->explode(',')->map(fn ($s) => trim($s))->filter()->values()->all();
        $data['work_start'] = $data['work_start'] ?: null;
        $data['work_end'] = $data['work_end'] ?: null;

        auth()->user()->update($data);
        $this->dispatch('notify', message: 'Profile saved.');
    }

    public function usePreset(int $index): void
    {
        [$this->status_emoji, $this->status_text, $minutes] = self::STATUS_PRESETS[$index] ?? ['', '', null];
        $this->status_clear = (string) ($minutes ?? '');
    }

    public function saveStatus(): void
    {
        $this->validate([
            'status_emoji' => ['nullable', 'string', 'max:16'],
            'status_text' => ['nullable', 'string', 'max:100'],
            'status_clear' => ['nullable', Rule::in(['', '30', '60', '120', '240', 'today', 'week'])],
        ]);

        $expires = match ($this->status_clear) {
            '' => null,
            'today' => auth()->user()->localTime()->endOfDay()->utc(),
            'week' => auth()->user()->localTime()->endOfWeek()->utc(),
            default => now()->addMinutes((int) $this->status_clear),
        };

        auth()->user()->update([
            'status_emoji' => $this->status_emoji ?: null,
            'status_text' => $this->status_text ?: null,
            'status_expires_at' => $expires,
        ]);

        $this->dispatch('notify', message: 'Status updated.');
    }

    public function clearStatus(): void
    {
        $this->reset('status_emoji', 'status_text', 'status_clear');
        auth()->user()->update(['status_emoji' => null, 'status_text' => null, 'status_expires_at' => null]);
        $this->dispatch('notify', message: 'Status cleared.');
    }

    public function updatedPhoto(EmployeeService $employees): void
    {
        $this->validate(['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:3072']]);
        $employees->setAvatar(auth()->user(), auth()->user(), $this->photo);
        $this->photo = null;
        $this->dispatch('notify', message: 'Photo updated.');
    }

    public function removePhoto(EmployeeService $employees): void
    {
        $employees->removeAvatar(auth()->user());
    }

    public function changePassword(ActivityLogger $activity): void
    {
        $key = 'change-password:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->resetErrorBag('current_password');
            $this->addError('current_password', 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return;
        }
        RateLimiter::hit($key, 5 * 60);

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(\App\Support\Settings::minPasswordLength()), 'different:current_password'],
        ]);

        $user = auth()->user();
        $user->update(['password' => Hash::make($this->password)]);
        RateLimiter::clear($key);

        // AuthenticateSession signs out every other device whose session holds the old
        // password hash; refresh this session's copy so the current device stays in.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
        $activity->log($user, 'auth.password', 'changed their password');

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('notify', message: 'Password changed.');
    }

    public function render()
    {
        return view('livewire.settings', [
            'timezones' => collect(timezone_identifiers_list())
                ->mapWithKeys(fn ($tz) => [$tz => str_replace('_', ' ', $tz).' ('.now()->setTimezone($tz)->format('P').')']),
            'localTime' => auth()->user()->localTime(),
        ]);
    }
}
