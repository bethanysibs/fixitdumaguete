<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['worker_profile_id', 'service_category_id', 'title', 'slug', 'summary', 'price_amount', 'is_active'])]
class Service extends Model
{
    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function formattedPrice(): string
    {
        $amount = (string) $this->price_amount;

        if (! str_contains($amount, '.')) {
            $amount .= '.00';
        }

        [$whole, $fraction] = explode('.', $amount, 2);

        return '₱'.number_format((int) $whole).'.'.substr(str_pad($fraction, 2, '0'), 0, 2);
    }

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
