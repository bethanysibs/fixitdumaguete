<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PaymentProof extends Model
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function disk(): string
    {
        return 'local';
    }

    public function canBeViewedBy(?User $user): bool
    {
        if ($user === null || $user->role === 'worker') {
            return false;
        }

        $this->loadMissing('payment');

        if ($user->isAdmin()) {
            return true;
        }

        return $user->role === 'customer' && $user->id === $this->payment?->customer_id;
    }

    protected static function booted(): void
    {
        static::saving(function (PaymentProof $proof): void {
            $path = (string) $proof->file_path;

            if ($path === '' || str_contains($path, '://') || str_starts_with($path, 'public/') || str_contains($path, '..')) {
                throw ValidationException::withMessages([
                    'file_path' => 'Payment proof stays in private storage.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
