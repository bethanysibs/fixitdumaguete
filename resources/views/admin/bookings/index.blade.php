@extends('layouts.app')

@section('title', 'Bookings | '.config('brand.name'))
@section('meta_description', 'Manage home-service bookings.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Bookings</h1>
            <p class="lede">Mark a visit pending, confirmed, in progress, done, or canceled. Canceling a recorded payment marks that payment refunded.</p>

            <form class="admin-filters mt-4" method="GET" action="{{ route('admin.bookings.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            @foreach (config('marketplace.statuses') as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <x-button type="submit" variant="secondary">Filter</x-button>
                    </div>
                </div>
            </form>

            <div class="table-responsive admin-table mt-4">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Visit</th>
                            <th>Customer</th>
                            <th>When</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr>
                                <td>
                                    <a href="{{ route('bookings.show', $booking) }}">{{ $booking->service->category->name }}</a>
                                    <div class="small text-muted">{{ $booking->workerProfile->user->name }} · {{ $booking->location->name }}</div>
                                </td>
                                <td>{{ $booking->customer->name }}</td>
                                <td>{{ $booking->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                <td>{{ $booking->payment ? ucfirst($booking->payment->status).' '.$booking->payment->formattedAmount() : 'Unpaid' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="d-flex gap-2">
                                            <label class="visually-hidden" for="status-{{ $booking->id }}">Status for booking {{ $booking->id }}</label>
                                            <select class="form-select" id="status-{{ $booking->id }}" name="status">
                                                @foreach (config('marketplace.statuses') as $value => $label)
                                                    <option value="{{ $value }}" @selected($booking->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-primary" type="submit">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No bookings match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $bookings->links() }}</div>
        </div>
    </section>
@endsection
