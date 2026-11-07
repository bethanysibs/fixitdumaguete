<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id',
    'item_type',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'sort_order',
])]
class QuotationItem extends Model
{
    public const TYPES = [
        'labor',
        'material',
        'service_charge',
        'inspection_fee',
        'other',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function typeLabel(): string
    {
        return match ($this->item_type) {
            'labor' => 'Labor',
            'material' => 'Material',
            'service_charge' => 'Service charge',
            'inspection_fee' => 'Inspection fee',
            'other' => 'Other',
            default => $this->item_type,
        };
    }

    public function formattedUnitPrice(): string
    {
        return Money::format((string) $this->unit_price);
    }

    public function formattedLineTotal(): string
    {
        return Money::format((string) $this->line_total);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
