<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Alert;
use App\Services\WhatsApp\WhatsAppSender;

class WhatsAppChannel
{
    public function __construct(private WhatsAppSender $sender) {}

    public function send(User $notifiable, Alert $notification): void
    {
        $this->sender->send($notifiable->phone, $notification->title, $notification->body, $notification->url, $notification->type, $notifiable);
    }
}
