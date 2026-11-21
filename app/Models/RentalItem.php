<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rental_id', 'item_id', 'item_name', 'price_per_day', 'qty', 'qty_returned_good', 'qty_damaged', 'qty_lost', 'subtotal'])]
class RentalItem extends Model
{
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }
}
