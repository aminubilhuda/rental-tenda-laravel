<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'address', 'id_type', 'id_number', 'notes', 'is_blacklisted', 'blacklist_reason'])]
class Customer extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_blacklisted' => 'boolean'];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }
}
