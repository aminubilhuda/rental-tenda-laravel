<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'category_id', 'name', 'description', 'purchase_price', 'rental_price_per_day', 'stock_total', 'stock_damaged', 'photo_path', 'is_active'])]
class Item extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['purchase_price' => 'integer', 'rental_price_per_day' => 'integer', 'stock_total' => 'integer', 'stock_damaged' => 'integer', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function rentedUnits(): int
    {
        if (array_key_exists('rented_units_cache', $this->attributes)) {
            return (int) $this->attributes['rented_units_cache'];
        }

        return (int) $this->rentalItems()->whereHas('rental', fn ($query) => $query->where('status', 'rented'))->selectRaw('COALESCE(SUM(qty - qty_returned_good - qty_damaged - qty_lost), 0) as units')->value('units');
    }

    public function availableStock(): int
    {
        return max(0, $this->stock_total - $this->stock_damaged - $this->rentedUnits());
    }
}
