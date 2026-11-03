<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class B2BAccountInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $companyName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('b2b.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Royal Travel B2B - Activate Your Account')
            ->greeting('Welcome to Royal Travel!')
            ->line(
                'Your company, ' . $this->companyName .
                ', has been registered and approved.'
            )
            ->line(
                'Your company administrator account is ready.'
            )
            ->line(
                'Use the secure link below to create your password.'
            )
            ->action('Set Your Password', $url)
            ->line(
                'This activation link expires after 30 minutes.'
            )
            ->line(
                'If you did not expect this invitation, please contact support.'
            )
            ->salutation('Royal Travel Team');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
