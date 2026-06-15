<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalUser extends Model
{
    protected $fillable = [
        'external_uuid',
        'first_name',
        'last_name',
        'email',
        'company_name',
        'position',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'external_user_id');
    }
}
