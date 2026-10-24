<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HelpRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $requestId, public string $name, public string $email, public string $site, public string $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SiteGuard help request #'.$this->requestId)
            ->line('From: '.$this->name.' <'.$this->email.'>')
            ->line('Site: '.$this->site)
            ->line($this->message);
    }
}
