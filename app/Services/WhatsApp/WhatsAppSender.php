<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends WhatsApp messages through the provider chosen in System settings.
 *
 *  - log:    test mode, writes to storage/logs (no account needed)
 *  - meta:   WhatsApp Cloud API. Business-initiated messages must use an
 *            approved template; ours has three body variables:
 *            {{1}} title, {{2}} message, {{3}} link.
 *  - twilio: Twilio WhatsApp. Free-form text works in the Twilio sandbox;
 *            production senders also need approved templates.
 *
 * Failures are recorded, never thrown, so a WhatsApp outage can't break the app.
 */
class WhatsAppSender
{
    public function send(string $phone, string $title, string $body, string $url, string $type = 'alert', ?User $user = null): WhatsappMessage
    {
        $driver = (string) Settings::get('whatsapp.driver');
        $to = self::normalise($phone);
        $text = "*{$title}*\n{$body}\n{$url}";

        $record = new WhatsappMessage([
            'user_id' => $user?->id,
            'to' => $to ?? $phone,
            'type' => $type,
            'body' => $text,
            'driver' => $driver,
            'status' => 'failed',
        ]);

        try {
            if (! $to) {
                throw new \InvalidArgumentException('Invalid phone number.');
            }

            [$status, $providerId] = match ($driver) {
                'meta' => $this->viaMeta($to, $title, $body, $url),
                'twilio' => $this->viaTwilio($to, $text),
                default => $this->viaLog($to, $text),
            };

            $record->fill(['status' => $status, 'provider_id' => $providerId]);
        } catch (Throwable $e) {
            $record->error = mb_substr($e->getMessage(), 0, 1000);
            Log::warning('WhatsApp send failed', ['to' => $to, 'error' => $e->getMessage()]);
        }

        $record->save();

        return $record;
    }

    /** "+91 98765-43210", "098765 43210" → "+919876543210" using the default country code. */
    public static function normalise(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $e164 = '+'.$digits;
        } elseif (str_starts_with($phone, '00')) {
            $e164 = '+'.substr($digits, 2);
        } else {
            $country = preg_replace('/\D+/', '', (string) Settings::get('whatsapp.default_country_code'));
            $e164 = '+'.$country.ltrim($digits, '0');
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $e164) ? $e164 : null;
    }

    private function viaLog(string $to, string $text): array
    {
        Log::info("[WhatsApp test mode] to {$to}:\n{$text}");

        return ['logged', null];
    }

    private function viaMeta(string $to, string $title, string $body, string $url): array
    {
        $phoneNumberId = Settings::get('whatsapp.meta_phone_number_id');
        $token = Settings::get('whatsapp.meta_token');

        if (! $phoneNumberId || ! $token) {
            throw new \RuntimeException('Meta WhatsApp is not configured (phone number ID and access token are required).');
        }

        $response = Http::withToken($token)->timeout(8)->acceptJson()
            ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($to, '+'),
                'type' => 'template',
                'template' => [
                    'name' => Settings::get('whatsapp.meta_template'),
                    'language' => ['code' => Settings::get('whatsapp.meta_language') ?: 'en'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => mb_substr($title, 0, 200)],
                            ['type' => 'text', 'text' => mb_substr(preg_replace('/\s+/', ' ', $body), 0, 900)],
                            ['type' => 'text', 'text' => $url],
                        ],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Meta API: '.($response->json('error.message') ?? $response->status()));
        }

        return ['sent', $response->json('messages.0.id')];
    }

    private function viaTwilio(string $to, string $text): array
    {
        $sid = Settings::get('whatsapp.twilio_sid');
        $token = Settings::get('whatsapp.twilio_token');
        $from = Settings::get('whatsapp.twilio_from');

        if (! $sid || ! $token || ! $from) {
            throw new \RuntimeException('Twilio WhatsApp is not configured (Account SID, auth token and sender number are required).');
        }

        $from = str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:'.self::normalise($from);

        $response = Http::withBasicAuth($sid, $token)->timeout(8)->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => 'whatsapp:'.$to,
                'Body' => $text,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Twilio API: '.($response->json('message') ?? $response->status()));
        }

        return ['sent', $response->json('sid')];
    }
}
