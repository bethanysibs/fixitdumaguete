<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commission_setting_id', 'service_category_id', 'rate'])]
class CommissionCategoryRate extends Model
{
    public function setting(): BelongsTo
    {
        return $this->belongsTo(CommissionSetting::class, 'commission_setting_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }
}
