@php
    $current = $booking->quotations->firstWhere('is_current', true);
    $canDecide = $booking->allowsNewQuotation() && $current && $current->status === 'admin_approved' && $current->admin_approved_at !== null;
    $visible = $booking->quotations->filter(fn ($quotation) => $quotation->visibleToCustomer());
@endphp

@extends('layouts.app')

@section('title', 'Quotation | '.config('brand.name'))
@section('meta_description', 'Review a quotation for your home-service visit.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'My bookings', 'url' => route('bookings.index')],
                ['label' => 'Quotation'],
            ]" />

            <h1 class="mt-3">Quotation</h1>
            <p class="lede">{{ $booking->workerProfile->user->name }} · {{ $booking->service->category->name }}</p>
            <p>Accepting a quotation agrees to that version and amount. It does not record a payment, change the booked price, or start the work.</p>

            @if ($booking->status === 'canceled')
                <div class="alert alert-info mt-3" role="status">This visit is canceled, so the quotation cannot be accepted or declined.</div>
            @elseif ($booking->status === 'completed')
                <div class="alert alert-info mt-3" role="status">This visit is done, so the quotation cannot be accepted or declined.</div>
            @endif

            @if ($visible->isEmpty())
                <p class="mt-3">A quotation is not ready for you to review yet.</p>
            @elseif (! $canDecide && (! $current || ! $current->visibleToCustomer()))
                <p class="mt-3">A new quotation is not ready for you to review yet.</p>
            @endif

            @foreach ($visible as $quotation)
                @include('quotations.customer-version', ['quotation' => $quotation])
            @endforeach

            @if ($current && $current->status === 'approved')
                <p class="mt-4"><a href="{{ route('bookings.payments.index', $booking) }}">Submit a payment</a></p>
            @endif

            @if ($canDecide)
                <p class="mt-4"><a href="{{ route('bookings.quotation.confirm', $booking) }}">Accept quotation</a></p>

                <form class="mt-3" method="POST" action="{{ route('bookings.quotation.reject', $booking) }}">
                    @csrf
                    <input type="hidden" name="version_number" value="{{ $current->version_number }}">
                    <x-form-textarea name="customer_rejection_reason" label="Reason for declining" hint="The worker and the platform can read this reason." rows="3" required />
                    <x-button type="submit" variant="secondary">Decline quotation</x-button>
                </form>
            @endif

            <p class="mt-4"><a href="{{ route('bookings.show', $booking) }}">Return to the visit</a></p>
        </div>
    </section>
@endsection
