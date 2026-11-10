<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\User;
use App\Workforce\WorkforceEvents;
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
        $user = $notifiable instanceof User ? $notifiable : null;
        $channels = NotificationPreference::channelsFor($this->organization, $this->event, $user);
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
        $name = $notifiable instanceof User && filled($notifiable->first_name)
            ? $notifiable->first_name
            : 'There';
        $brand = $this->organization->name;

        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$name.',')
            ->line($this->body)
            ->line('This notice is from '.$brand.'.')
            ->salutation('— '.$brand);

        if (filled($this->actionUrl)) {
            $mail->action($this->actionLabel ?: 'Open', $this->actionUrl);
        }

        return $mail;
    }

    /**
     * @return array{title: string, body: string, event: string, category: string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'event' => $this->event,
            'category' => WorkforceEvents::definition($this->event)['category'] ?? 'attendance',
            'url' => $this->actionUrl,
        ];
    }
}
