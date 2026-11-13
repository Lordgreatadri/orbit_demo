<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'organization_id',
    'user_id',
    'report_key',
    'format',
    'filters',
    'generation_scope',
    'status',
    'row_estimate',
    'failure_reason',
    'expires_at',
])]
class ReportExport extends Model
{
    use HasPublicUuid;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'generation_scope' => 'array',
            'row_estimate' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && filled($this->disk_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Claim a queued/failed row for processing once. Returns false when another
     * worker already claimed it, the org does not match, or it is already ready.
     */
    public static function claim(int $exportId, int $organizationId): ?self
    {
        $claimed = self::query()
            ->whereKey($exportId)
            ->where('organization_id', $organizationId)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_FAILED])
            ->update([
                'status' => self::STATUS_PROCESSING,
                'failure_reason' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return self::query()->whereKey($exportId)->first();
    }

    /**
     * Relative storage path owned by this export's organisation and requester.
     */
    public function artifactDirectory(): string
    {
        return 'report-exports/'.$this->organization_id.'/'.$this->user_id;
    }

    public function ownsDiskPath(?string $path): bool
    {
        if ($path === null || $path === '' || str_contains($path, '..')) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        $prefix = $this->artifactDirectory().'/';

        return str_starts_with($normalized, $prefix)
            && ! str_contains(substr($normalized, strlen($prefix)), '/');
    }

    public function deleteArtifact(): void
    {
        if ($this->disk_path && Storage::disk('local')->exists($this->disk_path)) {
            Storage::disk('local')->delete($this->disk_path);
        }
    }
}
