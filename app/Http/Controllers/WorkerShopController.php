<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerRating;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerShopController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', Rule::when($request->filled('min_price'), 'gte:min_price')],
            'sort' => ['nullable', 'in:featured,price_asc,price_desc,rating,name'],
        ]);

        $filters = [
            'q' => $this->blankToNull($filters['q'] ?? null),
            'category' => $this->blankToNull($filters['category'] ?? null),
            'location' => $this->blankToNull($filters['location'] ?? null),
            'min_price' => $filters['min_price'] ?? null,
            'max_price' => $filters['max_price'] ?? null,
            'sort' => $filters['sort'] ?? 'featured',
        ];

        $services = $this->listings($filters)
            ->paginate(9)
            ->withQueryString();

        return view('workers.index', [
            'services' => $services,
            'filters' => $filters,
            'categories' => ServiceCategory::query()->where('is_active', true)->orderBy('display_order')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'hasFilters' => $this->hasFilters($filters),
            'title' => 'Professionals | '.config('brand.name'),
            'description' => 'Browse sample professionals for each home service in Dumaguete City, Sibulan, and Dauin.',
        ]);
    }

    /**
     * @param  array{q: string|null, category: string|null, location: string|null, min_price: mixed, max_price: mixed, sort: string}  $filters
     */
    private function listings(array $filters): Builder
    {
        $query = Service::query()
            ->select('services.*')
            ->selectSub($this->ratingAverageQuery(), 'rating_average')
            ->selectSub($this->ratingCountQuery(), 'rating_count')
            ->where('is_active', true)
            ->whereHas('workerProfile', fn (Builder $profile) => $profile->approved())
            ->with(['category', 'workerProfile.user', 'workerProfile.locations']);

        if ($filters['q'] !== null) {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                    ->orWhereHas('workerProfile.user', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        }

        if ($filters['category'] !== null) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }

        if ($filters['location'] !== null) {
            $query->whereHas('workerProfile.locations', fn (Builder $location) => $location->where('slug', $filters['location']));
        }

        if ($filters['min_price'] !== null && $filters['min_price'] !== '') {
            $query->where('price_amount', '>=', $filters['min_price']);
        }

        if ($filters['max_price'] !== null && $filters['max_price'] !== '') {
            $query->where('price_amount', '<=', $filters['max_price']);
        }

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price_amount')->orderBy('services.id'),
            'price_desc' => $query->orderByDesc('price_amount')->orderBy('services.id'),
            'rating' => $query->orderByDesc('rating_average')->orderBy('services.id'),
            'name' => $query->orderBy(
                User::query()
                    ->select('users.name')
                    ->join('worker_profiles as name_profiles', 'name_profiles.user_id', '=', 'users.id')
                    ->whereColumn('name_profiles.id', 'services.worker_profile_id')
                    ->limit(1)
            )->orderBy('services.id'),
            default => $query->orderBy(
                ServiceCategory::query()
                    ->select('display_order')
                    ->whereColumn('service_categories.id', 'services.service_category_id')
                    ->limit(1)
            )->orderBy('services.id'),
        };

        return $query;
    }

    private function ratingAverageQuery(): Builder
    {
        return WorkerRating::query()
            ->selectRaw('coalesce(avg(score), 0)')
            ->whereColumn('worker_ratings.worker_profile_id', 'services.worker_profile_id');
    }

    private function ratingCountQuery(): Builder
    {
        return WorkerRating::query()
            ->selectRaw('count(*)')
            ->whereColumn('worker_ratings.worker_profile_id', 'services.worker_profile_id');
    }

    /**
     * @param  array{q: string|null, category: string|null, location: string|null, min_price: mixed, max_price: mixed, sort: string}  $filters
     */
    private function hasFilters(array $filters): bool
    {
        return $filters['q'] !== null
            || $filters['category'] !== null
            || $filters['location'] !== null
            || ($filters['min_price'] !== null && $filters['min_price'] !== '')
            || ($filters['max_price'] !== null && $filters['max_price'] !== '')
            || $filters['sort'] !== 'featured';
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
