<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'worker_profile_id',
    'service_id',
    'location_id',
    'scheduled_at',
    'notes',
    'latitude',
    'longitude',
    'address',
    'landmark',
    'contact_phone',
    'status',
    'price_amount',
    'commission_rate',
    'commission_amount',
    'worker_amount',
])]
class Booking extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BookingPhoto::class);
    }

    public function statusLabel(): string
    {
        return config('marketplace.statuses.'.$this->status, $this->status);
    }

    public function formattedPrice(): string
    {
        return Money::format((string) $this->price_amount);
    }

    public function visibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isAdmin()
            || $user->id === $this->customer_id
            || $user->id === $this->workerProfile?->user_id;
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'worker_amount' => 'decimal:2',
        ];
    }
}
