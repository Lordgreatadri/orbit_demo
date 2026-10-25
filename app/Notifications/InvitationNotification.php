<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The raw token is held only for the lifetime of this notification; it is
     * not persisted anywhere other than the e-mail itself.
     */
    public function __construct(
        public Invitation $invitation,
        #[\SensitiveParameter] public string $rawToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $roleLabel = config('orbit.roles.tenant.'.$this->invitation->role, $this->invitation->role);

        return (new MailMessage)
            ->subject(__('You are invited to join :org on :app', ['org' => $organization->name, 'app' => config('app.name')]))
            ->greeting(__('Hello,'))
            ->line(__(':org has invited you to join :app as :role.', ['org' => $organization->name, 'app' => config('app.name'), 'role' => $roleLabel]))
            ->action(__('Accept invitation'), $this->acceptUrl())
            ->line(__('This link can be used once and expires on :date.', ['date' => $this->invitation->expires_at->toDayDateTimeString()]))
            ->line(__('If you were not expecting this invitation, you can ignore this e-mail.'));
    }

    public function acceptUrl(): string
    {
        return route('invitations.show', ['token' => $this->rawToken]);
    }
}
