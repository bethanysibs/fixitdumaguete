<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'method',
    'account_name',
    'account_number',
    'bank_name',
    'qr_path',
    'instructions',
    'is_active',
])]
class PaymentInstruction extends Model
{
    public const METHODS = ['gcash', 'bank_transfer'];

    protected static function booted(): void
    {
        static::saving(function (PaymentInstruction $instruction): void {
            $path = (string) $instruction->qr_path;

            if ($path !== '' && (str_contains($path, '://') || str_contains($path, '..') || str_starts_with($path, 'public/'))) {
                throw ValidationException::withMessages([
                    'qr_path' => 'A QR image stays in private storage.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
