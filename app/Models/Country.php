<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    // Uses the project-wide HasPublicUuid trait (ADR-0003) instead of
    // dyrynda/laravel-model-uuid, which is not installed (see Stage 0 assessment).
    use HasFactory, HasPublicUuid;

    protected $fillable = ['name', 'code', 'currency', 'currency_symbol', 'country_currency', 'abbreviation', 'icon_url', 'uuid'];

    protected $casts = [
        'uuid' => 'string',
    ];

    // public function investors()
    // {
    //     return $this->hasMany(InvestorProfile::class, 'country');
    // }

    // public function users()
    // {
    //     return $this->hasMany(\App\Models\User::class, 'country_id');
    // }

    // // Backward-compatible aliases
    // public function investor()
    // {
    //     return $this->investors();
    // }

    // public function user()
    // {
    //     return $this->users();
    // }

}
