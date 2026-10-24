<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteScanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $domain, public string $target, public string $grade, public int $scanId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SiteGuard scan alert: '.$this->domain)
            ->line('Someone scanned '.$this->target.' with SiteGuard.')
            ->line('The public security report received grade '.$this->grade.'.')
            ->line('Scan reference: #'.$this->scanId)
            ->line('Sign in to SiteGuard to manage your alerts.');
    }
}
