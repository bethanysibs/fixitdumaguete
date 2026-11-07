<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['worker_profile_id', 'path', 'caption'])]
class WorkerPortfolioImage extends Model
{
    public function workerProfile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
