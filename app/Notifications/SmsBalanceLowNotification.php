<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SmsBalanceLowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Figures are copied at the moment the check decides to warn. A later
     * balance refresh must not change what this e-mail says.
     *
     * @param  list<array{purse: string, label: string, balance: string, unit: string, threshold: string}>  $reasons
     */
    public function __construct(
        public string $providerName,
        public string $providerUuid,
        public array $reasons,
    ) {}

    /**
     * Mail and the database only. Sending this by SMS would spend the balance it is warning about.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('ORBIT '.$this->providerName.' balance is low');

        foreach ($this->reasons as $reason) {
            $mail->line($reason['label'].' is '.$reason['balance'].' '.$reason['unit'].', which is at or below the threshold of '.$reason['threshold'].' '.$reason['unit'].'.');
        }

        return $mail
            ->line('Top up that balance with the provider so platform messages can keep going out.')
            ->action('Open SMS providers', route('admin.sms-providers.show', $this->providerUuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'provider' => $this->providerName,
            'provider_uuid' => $this->providerUuid,
            'reasons' => $this->reasons,
        ];
    }
}
