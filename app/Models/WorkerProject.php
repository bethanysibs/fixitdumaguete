<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['worker_profile_id', 'title', 'summary', 'completed_year'])]
class WorkerProject extends Model
{
    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    protected function casts(): array
    {
        return [
            'completed_year' => 'integer',
        ];
    }
}
