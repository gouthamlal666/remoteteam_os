<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\Settings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification. Every alert is stored in the database (bell +
 * browser pop-up); important ones are also emailed when the user has email
 * notifications turned on. Build these through App\Services\Notifier.
 */
class Alert extends Notification
{
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public string $url,
        public string $icon = '🔔',
        public bool $mail = false,
    ) {}

    public function via(User $notifiable): array
    {
        $channels = ['database'];

        if ($this->mail && ($notifiable->email_notifications ?? true) && Settings::get('notifications.email_enabled')) {
            $channels[] = 'mail';
        }

        if ($this->shouldWhatsApp($notifiable)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /** WhatsApp goes out when enabled org-wide, for this alert type, and the person opted in with a mobile number. */
    public function shouldWhatsApp(User $notifiable): bool
    {
        return (bool) Settings::get('notifications.whatsapp_enabled')
            && in_array($this->type, (array) Settings::get('whatsapp.events'), true)
            && $notifiable->whatsapp_notifications
            && filled($notifiable->phone);
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hi '.strtok($notifiable->name, ' ').',')
            ->line($this->body)
            ->action('Open in RemoteTeam OS', $this->url)
            ->line('You can turn off email notifications in Settings.')
            ->salutation('— '.Settings::companyName());
    }
}
