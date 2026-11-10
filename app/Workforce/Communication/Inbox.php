<?php

namespace App\Workforce\Communication;

use App\Models\DatabaseNotification;
use App\Models\NotificationReadAudit;
use App\Models\User;
use App\Workforce\WorkforceEvents;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in person's inbox. Every query is limited to that person and,
 * when they belong to an organisation, to that organisation.
 */
final class Inbox
{
    public static function unreadCount(User $user): int
    {
        return self::base($user)->whereNull('read_at')->count();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public static function recent(User $user, int $limit = 5): Collection
    {
        return self::base($user)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'data', 'read_at', 'created_at', 'event']);
    }

    public static function page(User $user, ?string $category): LengthAwarePaginator
    {
        $query = self::base($user)->latest('id');

        if ($category !== null && array_key_exists($category, WorkforceEvents::categories())) {
            $query->whereIn('event', WorkforceEvents::eventsIn($category));
        }

        return $query->paginate(15)->withQueryString();
    }

    public static function find(User $user, string $uuid): DatabaseNotification
    {
        return self::base($user)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * The first transition from unread to read writes one audit row.
     * A later click finds read_at already set and does not write again.
     */
    public static function markRead(User $user, DatabaseNotification $notification): void
    {
        if ($user->organization_id === null) {
            return;
        }

        DB::transaction(function () use ($user, $notification): void {
            $now = now();
            $updated = self::base($user)
                ->whereKey($notification->getKey())
                ->whereNull('read_at')
                ->update(['read_at' => $now, 'updated_at' => $now]);

            if ($updated !== 1) {
                return;
            }

            NotificationReadAudit::query()->insertOrIgnore([[
                'organization_id' => (int) $user->organization_id,
                'notification_id' => (int) $notification->getKey(),
                'user_id' => (int) $user->getKey(),
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        });
    }

    /**
     * Marks every unread notice for this user. Already-read notices are not
     * selected, so they are not audited again. The insert is one statement
     * per chunk of ids, not one query per notice.
     */
    public static function markAllRead(User $user): void
    {
        if ($user->organization_id === null) {
            return;
        }

        $now = now();

        self::base($user)
            ->whereNull('read_at')
            ->select('id')
            ->chunkById(200, function ($notifications) use ($user, $now): void {
                $ids = $notifications->map(fn (DatabaseNotification $notification): int => (int) $notification->getKey())->all();

                if ($ids === []) {
                    return;
                }

                DB::transaction(function () use ($user, $ids, $now): void {
                    $pending = self::base($user)
                        ->whereIn('id', $ids)
                        ->whereNull('read_at')
                        ->pluck('id');

                    if ($pending->isEmpty()) {
                        return;
                    }

                    self::base($user)
                        ->whereIn('id', $pending->all())
                        ->whereNull('read_at')
                        ->update(['read_at' => $now, 'updated_at' => $now]);

                    NotificationReadAudit::query()->insertOrIgnore(
                        $pending->map(fn ($id): array => [
                            'organization_id' => (int) $user->organization_id,
                            'notification_id' => (int) $id,
                            'user_id' => (int) $user->getKey(),
                            'read_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all(),
                    );
                });
            });
    }

    public static function safeUrl(?string $url, User $user): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $user->loadMissing('organization');
        $organization = $user->organization;

        if ($organization === null) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['user'])) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if ($host === null) {
            $path = $parts['path'] ?? '';

            return str_starts_with($path, '/org/') ? $path : null;
        }

        return strcasecmp($host, $organization->primaryHost()) === 0 ? $url : null;
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private static function base(User $user): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->when(
                $user->organization_id !== null,
                fn (Builder $query) => $query->where('organization_id', $user->organization_id),
            );
    }
}
