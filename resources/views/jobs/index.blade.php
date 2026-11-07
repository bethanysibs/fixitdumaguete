@extends('layouts.app')

@section('title', 'My jobs | '.config('brand.name'))
@section('meta_description', 'Visits assigned to your professional profile.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>My jobs</h1>
            <p class="lede">These are the visits customers booked with you. An admin updates the status.</p>

            @if ($bookings->isEmpty())
                <p class="mt-4">No visits have been booked with you yet.</p>
            @else
                <div class="table-responsive admin-table mt-4">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Customer</th>
                                <th>Where</th>
                                <th>When</th>
                                <th>Status</th>
                                <th>Your earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                <tr>
                                    <td><a href="{{ route('bookings.show', $booking) }}">{{ $booking->service->category->name }}</a></td>
                                    <td>{{ $booking->customer->name }}</td>
                                    <td>{{ $booking->location->name }}</td>
                                    <td>{{ $booking->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                    <td><span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></td>
                                    <td>{{ \App\Support\Money::format((string) $booking->worker_amount) }}</td>
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
