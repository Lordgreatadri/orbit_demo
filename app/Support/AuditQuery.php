<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\LoginEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared filtering for audit-trail and sign-in-history screens (tenant and
 * platform). Callers add their own tenant constraint before paginating.
 */
class AuditQuery
{
    /**
     * @return array{q: ?string, event: ?string, from: ?string, to: ?string, organization: ?string}
     */
    public static function filters(Request $request): array
    {
        return [
            'q' => $request->string('q')->trim()->toString() ?: null,
            'event' => $request->string('event')->toString() ?: null,
            'from' => self::validDate($request->input('from')),
            'to' => self::validDate($request->input('to')),
            'organization' => $request->string('organization')->toString() ?: null,
        ];
    }

    /**
     * @param  array{q: ?string, event: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<Activity>
     */
    public static function activities(array $filters): Builder
    {
        return Activity::query()
            ->with(['causer:id,first_name,last_name,email', 'organization:id,name'])
            ->when($filters['event'], fn ($q, $event) => $q->where('event', $event))
            ->when($filters['from'], fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['q'], function ($q, $term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($w) use ($like): void {
                    $w->whereRaw('LOWER(description) LIKE ?', [$like])
                        ->orWhereHas('causer', fn ($c) => $c
                            ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]));
                });
            })
            ->latest('id');
    }

    /**
     * @param  array{q: ?string, event: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<LoginEvent>
     */
    public static function loginEvents(array $filters): Builder
    {
        return LoginEvent::query()
            ->with(['user:id,uuid,first_name,last_name,email,organization_id', 'organization:id,name'])
            ->ofType($filters['event'])
            ->when($filters['from'], fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['q'], function ($q, $term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($w) use ($like): void {
                    $w->whereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                        ->orWhere('ip_address', 'like', $like);
                });
            })
            ->latest('created_at')
            ->latest('id');
    }

    /**
     * Distinct event names for the filter dropdown.
     *
     * @param  Builder<Activity>  $scope
     * @return array<string, string>
     */
    public static function eventOptions(Builder $scope): array
    {
        return $scope->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')
            ->mapWithKeys(fn (string $event) => [$event => $event])
            ->all();
    }

    /**
     * Counts for the last 24 hours and 7 days in one grouped query each.
     *
     * @param  Builder<LoginEvent>  $scope
     * @return array<string, array<string, int>>
     */
    public static function loginSummary(Builder $scope, string $timezone): array
    {
        $today = (clone $scope)
            ->where('created_at', '>=', now($timezone)->startOfDay()->utc())
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        $week = (clone $scope)
            ->where('created_at', '>=', now()->subDays(7))
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        return [
            'today' => ['login' => (int) ($today['login'] ?? 0), 'failed' => (int) ($today['failed'] ?? 0), 'lockout' => (int) ($today['lockout'] ?? 0)],
            'week' => ['login' => (int) ($week['login'] ?? 0), 'failed' => (int) ($week['failed'] ?? 0), 'lockout' => (int) ($week['lockout'] ?? 0)],
        ];
    }

    private static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)?->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
