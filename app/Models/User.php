<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'role', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(WorkerRating::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function canRateWorkers(): bool
    {
        return in_array($this->role, ['customer', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
