<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkforceMessage extends Notification
{
    public function __construct(
        public Organization $organization,
        public string $event,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = NotificationPreference::channelsFor($this->organization, $this->event);
        $via = [];

        if ($channels['database']) {
            $via[] = 'database';
        }

        if ($channels['mail']) {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if (filled($this->actionUrl)) {
            $mail->action($this->actionLabel ?: 'Open', $this->actionUrl);
        }

        return $mail;
    }

    /**
     * @return array{title: string, body: string, event: string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'event' => $this->event,
            'url' => $this->actionUrl,
        ];
    }

    public function shouldSendSms(User $user): bool
    {
        return NotificationPreference::channelsFor($this->organization, $this->event)['sms']
            && filled($user->phone);
    }
}
