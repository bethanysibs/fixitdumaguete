@extends('layouts.app')

@section('title', $title)
@section('meta_description', $description)
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="section-block worker-profile">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Professionals', 'url' => route('workers.index')],
                ['label' => $workerProfile->user->name],
            ]" />

            @if ($workerProfile->is_sample)
                <p class="sample-banner mt-4 mb-4">Sample profile. This person is not a real {{ config('brand.name') }} worker. A booking here is a preview, the payment is recorded on the site, and this page does not list a phone number or street address.</p>
            @endif

            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="d-flex gap-3 align-items-start">
                        <x-profile-photo :profile="$workerProfile" class="profile-photo-profile" />
                        <div>
                            <h1>{{ $workerProfile->user->name }}</h1>
                            <p class="lede mb-2">{{ $workerProfile->headline }}</p>
                            @auth
                                @if (auth()->id() === $workerProfile->user_id)
                                    <a href="{{ route('work.edit') }}">Edit profile</a>
                                @elseif (auth()->user()->isAdmin())
                                    <a href="{{ route('admin.workers.edit', $workerProfile) }}">Edit profile</a>
                                @endif
                            @endauth
                        </div>
                    </div>
                    <x-star-rating class="my-3" :average="$workerProfile->ratings_avg_score" :count="$workerProfile->ratings_count" />
                    <p>{{ $workerProfile->bio }}</p>
                    @if ($workerProfile->years_experience)
                        <p class="mb-1"><strong>Experience:</strong> {{ $workerProfile->years_experience }} {{ $workerProfile->years_experience === 1 ? 'year' : 'years' }}</p>
                    @endif
                    @if ($workerProfile->availability_note)
                        <p class="mb-1"><strong>Availability:</strong> {{ $workerProfile->availability_note }}</p>
                    @endif
                    <p class="mb-0"><strong>Service area:</strong> {{ $workerProfile->locations->pluck('name')->join(', ') }}, {{ $workerProfile->locations->pluck('province')->unique()->join(', ') }}</p>
                    @if ($workerProfile->skills->isNotEmpty())
                        <h2 class="h5 mt-4">Skills</h2>
                        <div class="skill-list">
                            @foreach ($workerProfile->skills as $skill)
                                <span class="skill-pill">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if ($workerProfile->projects->isNotEmpty())
                        <h2 class="h5 mt-4">Projects</h2>
                        @foreach ($workerProfile->projects as $project)
                            <article class="mb-3">
                                <h3 class="h6 mb-1">{{ $project->title }}@if ($project->completed_year) · {{ $project->completed_year }}@endif</h3>
                                <p class="mb-0">{{ $project->summary }}</p>
                            </article>
                        @endforeach
                    @endif
                    @if ($workerProfile->portfolioImages->isNotEmpty())
                        <h2 class="h5 mt-4">{{ $workerProfile->is_sample ? 'Sample portfolio' : 'Portfolio' }}</h2>
                        <div class="portfolio-grid">
                            @foreach ($workerProfile->portfolioImages as $image)
                                <img src="{{ $image->url() }}" alt="{{ $image->caption ?: 'Portfolio photo' }}">
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-lg-5">
                    @foreach ($workerProfile->services as $service)
                        <article class="worker-offer mb-4">
                            @if ($workerProfile->is_sample)
                                <span class="sample-badge">Sample</span>
                            @endif
                            <h2>{{ $service->category->name }}</h2>
                            <p>{{ $service->summary }}</p>
                            <p class="worker-price">From {{ $service->formattedPrice() }}</p>
                            @auth
                                @if (auth()->user()->role === 'customer')
                                    <x-button href="{{ route('bookings.create', ['workerProfile' => $workerProfile, 'service' => $service->id]) }}">Book this service</x-button>
                                @else
                                    <p class="small muted mb-0">Customers book this service from their own account.</p>
                                @endif
                            @else
                                <x-button href="{{ route('bookings.create', ['workerProfile' => $workerProfile, 'service' => $service->id]) }}">Log in to book</x-button>
                            @endauth
                        </article>
                    @endforeach

                    @include('workers.rating')
                </div>
            </div>
        </div>
    </section>
@endsection
