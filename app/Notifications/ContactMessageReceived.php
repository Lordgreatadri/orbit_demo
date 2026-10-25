<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->contactMessage;

        return (new MailMessage)
            ->subject(__('[:app] Contact: :subject', ['app' => config('app.name'), 'subject' => $message->subject]))
            ->replyTo($message->email, $message->name)
            ->line(__('A new contact message was submitted on the public website.'))
            ->line(__('From: :name <:email>', ['name' => $message->name, 'email' => $message->email]))
            ->when($message->organization_name, fn (MailMessage $mail) => $mail->line(__('Organisation: :org', ['org' => $message->organization_name])))
            ->line(__('Subject: :subject', ['subject' => $message->subject]))
            ->line($message->message)
            ->line(__('Reference: :uuid', ['uuid' => $message->uuid]));
    }
}
