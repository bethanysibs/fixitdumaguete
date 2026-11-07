<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'path'])]
class BookingPhoto extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
