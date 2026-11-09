<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'location_id', 'label', 'bssid', 'is_active'])]
class WifiNetwork extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public static function normalizeBssid(string $value): string
    {
        $hex = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $value) ?? '');

        if (strlen($hex) !== 12) {
            return '';
        }

        return implode(':', str_split($hex, 2));
    }
}
