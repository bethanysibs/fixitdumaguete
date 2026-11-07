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
                <p class="sample-banner mt-4 mb-4">Sample profile. This person is not a real FixItDumaguete worker. Booking is not open, and this page does not list a phone number or street address.</p>
            @endif

            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <h1>{{ $workerProfile->user->name }}</h1>
                    <p class="lede">{{ $workerProfile->headline }}</p>
                    <x-star-rating class="mb-3" :average="$workerProfile->ratings_avg_score" :count="$workerProfile->ratings_count" />
                    <p>{{ $workerProfile->bio }}</p>
                    <p class="mb-0"><strong>Service area:</strong> {{ $workerProfile->locations->pluck('name')->join(', ') }}, {{ $workerProfile->locations->pluck('province')->unique()->join(', ') }}</p>
                </div>
                <div class="col-lg-5">
                    @foreach ($workerProfile->services as $service)
                        <article class="worker-offer mb-4">
                            @if ($workerProfile->is_sample)
                                <span class="sample-badge">Sample</span>
                            @endif
                            <h2>{{ $service->category->name }}</h2>
                            <p>{{ $service->summary }}</p>
                            <p class="worker-price mb-0">From {{ $service->formattedPrice() }}</p>
                        </article>
                    @endforeach

                    @include('workers.rating')
                </div>
            </div>
        </div>
    </section>
@endsection
