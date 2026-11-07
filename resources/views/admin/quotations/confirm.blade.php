@extends('layouts.app')

@section('title', 'Approve quotation | '.config('brand.name'))
@section('meta_description', 'Approve a quotation for the customer to review.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Approve for customer review</h1>
            <p class="lede">Approve version {{ $quotation->version_number }} so the customer can review it later. This does not mean the customer accepted the price, a payment was recorded, the work is authorized, or the worker will be paid.</p>

            @include('quotations.version', ['quotation' => $quotation])

            <form class="mt-4" method="POST" action="{{ route('admin.quotations.approve', $booking) }}">
                @csrf
                <input type="hidden" name="quotation_request_id" value="{{ $quotation->quotation_request_id }}">
                <x-button type="submit">Approve for customer review</x-button>
            </form>
            <p class="mt-3"><a href="{{ route('admin.quotations.show', $booking) }}">Return to the quotation</a></p>
        </div>
    </section>
@endsection
