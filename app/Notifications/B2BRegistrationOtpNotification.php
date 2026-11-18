<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class B2BRegistrationOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp,
        private readonly string $companyId
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = 'https://staging.royalis.tech/activate-account?'
            . http_build_query([
                'email' => $notifiable->getEmailForVerification(),
                'company_id' => $this->companyId,
            ]);

        return (new MailMessage)
            ->subject('Royal Travel — Activate Your Company Account')
            ->greeting('Your Company Has Been Approved!')
            ->line('Your company registration has been approved.')
            ->line('Enter the following verification code to activate your account:')
            ->line('**' . $this->otp . '**')
            ->action('Activate My Account', $activationUrl)
            ->line('This code expires in 5 minutes.')
            ->line('If the code expires, request a new one from the activation page.')
            ->line('Do not share your verification code with anyone.');
    }
}
