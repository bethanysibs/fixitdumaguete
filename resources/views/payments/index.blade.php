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
                    @if ($canSubmit)
                        <div class="d-grid gap-2 d-sm-flex mt-3">
                            @if ($canPayDeposit)
                                <a class="btn btn-primary" href="{{ route('bookings.payments.create', [$booking, 'type' => 'deposit']) }}">Pay the deposit</a>
                            @endif
                            <a class="btn btn-outline-primary" href="{{ route('bookings.payments.create', [$booking, 'type' => 'balance']) }}">Pay part of it</a>
                            <a class="btn btn-outline-primary" href="{{ route('bookings.payments.create', [$booking, 'type' => 'full_payment']) }}">Pay what I still owe</a>
                        </div>
                    @endif
                    @if ($hasRejected)
                        <p class="form-text mt-3">A rejected receipt is not paid. You can send another one for the amount still available.</p>
                    @endif
                    @include('payments.history')
                </div>
                <div class="col-lg-5">
                    @include('payments.instructions')
                </div>
            </div>

            <p class="mt-4"><a href="{{ route('bookings.show', $booking) }}">Return to the visit</a></p>
        </div>
    </section>
@endsection
