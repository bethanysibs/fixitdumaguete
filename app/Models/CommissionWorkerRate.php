<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commission_setting_id', 'worker_profile_id', 'rate'])]
class CommissionWorkerRate extends Model
{
    public function setting(): BelongsTo
    {
        return $this->belongsTo(CommissionSetting::class, 'commission_setting_id');
    }

    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }
}
