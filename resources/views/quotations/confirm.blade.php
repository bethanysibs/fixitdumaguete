@extends('layouts.app')

@section('title', 'Submit quotation | '.config('brand.name'))
@section('meta_description', 'Confirm a quotation before sending it for admin review.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Submit quotation</h1>
            <p class="lede">Submit version {{ $quotation->version_number }} for admin review. This version cannot be edited afterward. A revision would be saved as a new draft.</p>

            @include('quotations.version', ['quotation' => $quotation])

            <form class="mt-4" method="POST" action="{{ route('jobs.quotation.submit', $booking) }}">
                @csrf
                <x-button type="submit">Submit for admin review</x-button>
            </form>
            <p class="mt-3"><a href="{{ route('bookings.show', $booking) }}">Return to the visit</a></p>
        </div>
    </section>
@endsection
