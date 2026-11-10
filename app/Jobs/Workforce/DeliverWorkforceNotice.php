<?php

namespace App\Jobs\Workforce;

use App\Jobs\Concerns\IsTenantAware;
use App\Models\User;
use App\Workforce\WorkforceNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one workforce notice after the business transaction has committed.
 * Mail, the database inbox, push, and SMS are attempted here. A provider
 * failure retries this job and does not roll back the leave or schedule row.
 */
class DeliverWorkforceNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, IsTenantAware, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        int $organizationId,
        public int $userId,
        public string $event,
        public string $title,
        public string $body,
        public string $dedupeKey,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {
        $this->organizationId = $organizationId;
    }

    public function handle(WorkforceNotifier $notifier): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $notifier->deliver(
            $user,
            $this->event,
            $this->title,
            $this->body,
            $this->dedupeKey,
            $this->actionUrl,
            $this->actionLabel,
        );
    }
}
