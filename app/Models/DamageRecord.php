<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'rental_id', 'rental_item_id', 'customer_id', 'item_id', 'type', 'qty', 'charge_amount', 'paid_amount', 'status', 'resolution', 'photo_path', 'notes', 'written_off_reason'])]
class DamageRecord extends Model
{
    use SoftDeletes;

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DamagePayment::class);
    }
}
