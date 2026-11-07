<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'slug', 'headline', 'bio', 'years_experience', 'availability_note', 'photo_path', 'verification_status', 'is_sample'])]
class WorkerProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(WorkerRating::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(WorkerSkill::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(WorkerProject::class);
    }

    public function portfolioImages(): HasMany
    {
        return $this->hasMany(WorkerPortfolioImage::class);
    }

    public function photoUrl(): ?string
    {
        if ($this->photo_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'W';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('verification_status', 'approved');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'is_sample' => 'boolean',
            'years_experience' => 'integer',
        ];
    }
}
