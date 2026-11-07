@extends('layouts.app')

@section('title', 'Payments | '.config('brand.name'))
@section('meta_description', 'Check customer payment receipts.')

@section('content')
    <section class="section-block">
        <div class="container">
            <h1>Payments</h1>
            <p class="lede">Receipts waiting to be checked are listed first. A receipt is not paid until you mark it paid.</p>

            <form class="admin-filters mt-4" method="GET" action="{{ route('admin.payments.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="q">Booking or customer</label>
                        <input class="form-control" id="q" name="q" value="{{ $search }}" placeholder="Booking number or customer name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending_review" @selected($status === 'pending_review')>Waiting for verification</option>
                            <option value="verified" @selected($status === 'verified')>Paid</option>
                            <option value="rejected" @selected($status === 'rejected')>Payment rejected</option>
                            <option value="paid" @selected($status === 'paid')>Recorded on the platform</option>
                            <option value="all" @selected($status === 'all')>All payments</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <x-button type="submit" variant="secondary">Search</x-button>
                    </div>
                </div>
            </form>

            <div class="table-responsive admin-table mt-4">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Visit</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($claims as $claim)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.payments.show', $claim) }}">Booking {{ $claim->booking_id }}</a>
                                    <div class="small text-muted">{{ $claim->booking->service->category->name }}</div>
                                </td>
                                <td>{{ $claim->customer->name }}</td>
                                <td>{{ $claim->formattedAmount() }}</td>
                                <td>{{ $claim->payment_reference ?: 'Not entered' }}</td>
                                <td>{{ $claim->customerStatusLabel() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No payments match this search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $claims->links() }}</div>
        </div>
    </section>
@endsection
