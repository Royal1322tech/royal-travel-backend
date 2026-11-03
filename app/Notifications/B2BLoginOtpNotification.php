<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class B2BLoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Royal Travel - Login Verification Code')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your Royal Travel B2B verification code is:')
            ->line('**' . $this->otp . '**')
            ->line('This code expires in 5 minutes.')
            ->line('If you did not request this login, ignore this email.');
    }
}
