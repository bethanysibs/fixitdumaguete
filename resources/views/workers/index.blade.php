@extends('layouts.app')

@section('title', $title)
@section('meta_description', $description)
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="section-block workers-section">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Professionals'],
            ]" />

            <div class="row section-heading mt-4">
                <div class="col-lg-8">
                    <h1>A sample professional for each service</h1>
                    <p class="lede mb-0">Search the preview professionals, then narrow the list by service, municipality, or price. Every sample card is labeled. None of these people can be booked yet, and a star rating appears only after a customer or an admin sets one.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3">
                    <form class="shop-filters" method="GET" action="{{ route('workers.index') }}">
                        <h2 class="h5">Filter</h2>
                        <x-form-input name="q" label="Search" :value="$filters['q']" placeholder="Name or service" />
                        <div class="mb-3">
                            <label class="form-label" for="category">Service</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All services</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="location">Area</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">All areas</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->slug }}" @selected($filters['location'] === $location->slug)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-input name="min_price" label="Minimum price" type="number" :value="$filters['min_price']" min="0" step="0.01" inputmode="decimal" hint="Philippine pesos" />
                        <x-form-input name="max_price" label="Maximum price" type="number" :value="$filters['max_price']" min="0" step="0.01" inputmode="decimal" />
                        <div class="mb-3">
                            <label class="form-label" for="sort">Sort</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="featured" @selected($filters['sort'] === 'featured')>Catalog order</option>
                                <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price: low to high</option>
                                <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price: high to low</option>
                                <option value="rating" @selected($filters['sort'] === 'rating')>Highest rated</option>
                                <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                            </select>
                        </div>
                        <button class="btn btn-secondary" type="submit">Apply filters</button>
                        @if ($hasFilters)
                            <a class="shop-clear" href="{{ route('workers.index') }}">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="col-lg-9">
                    <p class="shop-count">{{ $services->total() }} {{ $services->total() === 1 ? 'professional' : 'professionals' }}</p>
                    @if ($services->isEmpty())
                        <x-empty-state
                            :title="$hasFilters ? 'No professionals match these filters' : 'No professionals are listed yet'"
                            :message="$hasFilters ? 'Try a different service, area, or price range.' : 'Approved workers will appear here.'"
                        />
                    @else
                        <div class="row g-4">
                            @foreach ($services as $service)
                                <div class="col-sm-6 col-xl-4">
                                    <article class="worker-card">
                                        @if ($service->workerProfile->is_sample)
                                            <span class="sample-badge">Sample</span>
                                        @endif
                                        <h2>
                                            <a href="{{ route('workers.show', $service->workerProfile) }}">{{ $service->workerProfile->user->name }}</a>
                                        </h2>
                                        <p class="worker-meta mb-1">{{ $service->category->name }}</p>
                                        <p class="worker-meta mb-2">{{ $service->workerProfile->locations->pluck('name')->join(', ') }}</p>
                                        <x-star-rating :average="$service->rating_average" :count="$service->rating_count" />
                                        <p class="worker-price mb-3">From {{ $service->formattedPrice() }}</p>
                                        <a class="worker-link" href="{{ route('workers.show', $service->workerProfile) }}">View profile</a>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $services->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
