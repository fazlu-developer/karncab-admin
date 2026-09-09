<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperatorRoleAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $role) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('KarnaCab role assigned')
            ->line("Your operations role is now {$this->role}.");
    }
}
