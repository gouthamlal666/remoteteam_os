<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Organisation-wide settings stored in the `settings` table (JSON values),
 * cached, with defaults. Secrets (API tokens) are encrypted at rest and are
 * never sent back to the browser.
 */
class Settings
{
    public const DEFAULTS = [
        'general.company_name' => 'RemoteTeam OS',
        'general.logo_path' => null,
        'general.default_timezone' => 'Asia/Kolkata',

        'work.working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'work.day_start' => '09:00',
        'work.day_end' => '18:00',
        'work.report_reminder_enabled' => false,
        'work.report_reminder_time' => '18:00',

        'notifications.email_enabled' => true,
        'notifications.whatsapp_enabled' => false,
        'whatsapp.driver' => 'log',
        'whatsapp.default_country_code' => '+91',
        'whatsapp.events' => ['task.assigned', 'task.approved', 'task.revision', 'task.due', 'meeting.invited', 'meeting.soon', 'meeting.cancelled', 'report.reminder'],
        'whatsapp.meta_phone_number_id' => null,
        'whatsapp.meta_token' => null,
        'whatsapp.meta_template' => 'remoteteam_alert',
        'whatsapp.meta_language' => 'en',
        'whatsapp.twilio_sid' => null,
        'whatsapp.twilio_token' => null,
        'whatsapp.twilio_from' => null,

        'security.min_password_length' => 8,
        'security.member_direct_messages' => true,
    ];

    public const SECRETS = ['whatsapp.meta_token', 'whatsapp.twilio_token'];

    private const CACHE_KEY = 'system-settings';

    /** Alerts that can be sent to WhatsApp, with labels for the settings page. */
    public const WHATSAPP_EVENTS = [
        'task.assigned' => 'Task assigned',
        'task.approved' => 'Task approved',
        'task.revision' => 'Task sent back for revision',
        'task.due' => 'Task due tomorrow',
        'task.submitted' => 'Task submitted for review (to managers)',
        'meeting.invited' => 'Meeting invitation',
        'meeting.soon' => 'Meeting starts in 15 minutes',
        'meeting.cancelled' => 'Meeting cancelled',
        'report.reminder' => 'Daily report reminder',
        'chat.direct' => 'New private message',
        'chat.mention' => 'Mentioned in chat',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @param array<string, mixed> $values */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                // Blank means "keep the saved secret".
                if (blank($value)) {
                    continue;
                }
                $value = Crypt::encryptString((string) $value);
            }

            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function forgetSecret(string $key): void
    {
        Setting::whereKey($key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    public static function hasSecret(string $key): bool
    {
        return filled(self::all()[$key] ?? null);
    }

    /** @return array<string, mixed> stored values merged over defaults, secrets decrypted */
    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::pluck('value', 'key')->map(fn ($v) => json_decode((string) $v, true))->all();
            } catch (Throwable) {
                return [];
            }
        });

        foreach (self::SECRETS as $secret) {
            if (isset($stored[$secret])) {
                $stored[$secret] = rescue(fn () => Crypt::decryptString($stored[$secret]), null, false);
            }
        }

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function companyName(): string
    {
        return (string) (self::get('general.company_name') ?: 'RemoteTeam OS');
    }

    /** @return array<int, string> e.g. ['mon', 'tue', ...] */
    public static function workingDays(): array
    {
        return (array) self::get('work.working_days');
    }

    public static function isWorkingDay(\DateTimeInterface $date): bool
    {
        return in_array(strtolower($date->format('D')), self::workingDays(), true);
    }

    public static function minPasswordLength(): int
    {
        return max(8, (int) self::get('security.min_password_length'));
    }
}
