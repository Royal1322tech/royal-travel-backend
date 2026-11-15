<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class B2BRegistrationOtpNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $otp) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Royal Travel — Verify your company email')
            ->line('Your company registration verification code is:')
            ->line('**' . $this->otp . '**')
            ->line('This code expires in 5 minutes. Do not share it.');
    }
}
