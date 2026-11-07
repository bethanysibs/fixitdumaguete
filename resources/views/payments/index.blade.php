@extends('layouts.app')

@section('title', 'Payments | '.config('brand.name'))
@section('meta_description', 'Payment claims for a home-service visit.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'My bookings', 'url' => route('bookings.index')],
                ['label' => 'Payments'],
            ]" />

            <h1 class="mt-3">Payments</h1>
            <p class="lede">{{ $booking->workerProfile->user->name }} · {{ $booking->service->category->name }}</p>

            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    @include('payments.summary')
                    @include('payments.history')
                    @if ($canSubmit)
                        <p class="mt-3"><a href="{{ route('bookings.payments.create', $booking) }}">Submit a payment claim</a></p>
                    @endif
                </div>
                <div class="col-lg-5">
                    @include('payments.instructions')
                </div>
            </div>

            <p class="mt-4"><a href="{{ route('bookings.show', $booking) }}">Return to the visit</a></p>
        </div>
    </section>
@endsection
