<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['damage_record_id', 'amount', 'method', 'paid_at', 'user_id', 'notes'])]
class DamagePayment extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
