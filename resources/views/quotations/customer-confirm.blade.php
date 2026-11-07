@extends('layouts.app')

@section('title', 'Accept quotation | '.config('brand.name'))
@section('meta_description', 'Confirm acceptance of a specific quotation.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Accept quotation</h1>
            <p class="lede">You are accepting version {{ $quotation->version_number }} for {{ $quotation->formattedTotal() }}. This accepts that quotation only. It does not record a payment, change the booked price, or start the work.</p>

            @include('quotations.customer-version', ['quotation' => $quotation])

            <form class="mt-4" method="POST" action="{{ route('bookings.quotation.accept', $booking) }}">
                @csrf
                <input type="hidden" name="quotation_request_id" value="{{ $quotation->quotation_request_id }}">
                <input type="hidden" name="version_number" value="{{ $quotation->version_number }}">
                <x-button type="submit">Accept version {{ $quotation->version_number }}</x-button>
            </form>
            <p class="mt-3"><a href="{{ route('bookings.quotation.show', $booking) }}">Return to the quotation</a></p>
        </div>
    </section>
@endsection
