@extends('layouts.app')

@section('title', 'My bookings | '.config('brand.name'))
@section('meta_description', 'Visits you have booked on '.config('brand.name').'.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>My bookings</h1>
            <p class="lede">Each visit stays pending until you record the payment. An admin can then mark it in progress, done, or canceled.</p>

            @if ($bookings->isEmpty())
                <p class="mt-4">You have no bookings yet. <a href="{{ route('workers.index') }}">Choose a professional</a> to request a visit.</p>
            @else
                <div class="table-responsive admin-table mt-4">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Professional</th>
                                <th>When</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                <tr>
                                    <td><a href="{{ route('bookings.show', $booking) }}">{{ $booking->service->category->name }}</a></td>
                                    <td>{{ $booking->workerProfile->user->name }}</td>
                                    <td>{{ $booking->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                    <td><span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></td>
                                    <td>{{ $booking->payment?->status ? ucfirst($booking->payment->status) : 'Unpaid' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $bookings->links() }}</div>
            @endif
        </div>
    </section>
@endsection
