<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['trx_code', 'checkout_token', 'customer_id', 'user_id', 'rent_date', 'due_date', 'returned_at', 'duration_days', 'subtotal', 'discount', 'total', 'late_fee', 'guarantee_type', 'deposit_amount', 'deposit_returned', 'deposit_deducted', 'customer_photo_path', 'status', 'notes', 'cancelled_reason'])]
class Rental extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['rent_date' => 'date', 'due_date' => 'date', 'returned_at' => 'datetime', 'duration_days' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(DamageRecord::class);
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'rented' && $this->due_date->isPast() && ! $this->due_date->isToday() ? 'late' : $this->status;
    }
}
