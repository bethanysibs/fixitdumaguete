@extends('layouts.app')

@section('title', 'Booking | '.config('brand.name'))
@section('meta_description', 'A home-service booking on '.config('brand.name').'.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => auth()->user()->role === 'worker' ? 'My jobs' : 'My bookings', 'url' => auth()->user()->role === 'worker' ? route('jobs.index') : (auth()->user()->isAdmin() ? route('admin.bookings.index') : route('bookings.index'))],
                ['label' => $booking->service->category->name],
            ]" />

            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    <p class="eyebrow mb-2"><span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></p>
                    <h1>{{ $booking->service->category->name }}</h1>
                    <p class="lede">{{ $booking->workerProfile->user->name }} · {{ $booking->location->name }}</p>
                    <dl class="account-details">
                        <div>
                            <dt>When</dt>
                            <dd>{{ $booking->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                        </div>
                        <div>
                            <dt>Customer</dt>
                            <dd>{{ $booking->customer->name }}</dd>
                        </div>
                        <div>
                            <dt>Problem</dt>
                            <dd>{{ $booking->notes }}</dd>
                        </div>
                        <div>
                            <dt>Address</dt>
                            <dd>{{ $booking->address ?: 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt>How to find the home</dt>
                            <dd>{{ $booking->landmark ?: 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt>Mobile number</dt>
                            <dd>{{ $booking->contact_phone ?: 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt>Price</dt>
                            <dd>{{ $booking->formattedPrice() }}</dd>
                        </div>
                        @if (auth()->user()->isAdmin() || auth()->id() === $booking->workerProfile->user_id)
                            <div>
                                <dt>Platform commission</dt>
                                <dd>{{ \App\Support\Money::format((string) $booking->commission_amount) }} ({{ $booking->commission_original_rate ?: $booking->commission_rate }}%)</dd>
                            </div>
                            @if ($booking->commission_rule)
                                <div>
                                    <dt>Commission rule</dt>
                                    <dd>{{ $booking->commissionRuleLabel() }}@if ($booking->commission_setting_id) · version {{ $booking->commission_setting_id }}@endif</dd>
                                </div>
                                <div>
                                    <dt>Calculation base</dt>
                                    <dd>{{ \App\Support\Money::format((string) $booking->commission_base_amount) }} · {{ $booking->commission_basis === 'entire_service' ? 'Entire service amount' : $booking->commission_basis }}</dd>
                                </div>
                                <div>
                                    <dt>Minimum or maximum adjustment</dt>
                                    <dd>{{ $booking->commission_adjustment !== null ? \App\Support\Money::format((string) $booking->commission_adjustment) : 'None' }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>Worker earnings</dt>
                                <dd>{{ \App\Support\Money::format((string) $booking->worker_amount) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>Payment</dt>
                            <dd>
                                @if ($booking->payment)
                                    {{ ucfirst($booking->payment->status) }} · {{ $booking->payment->formattedAmount() }} recorded on the platform
                                @else
                                    Not recorded yet
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if (auth()->user()->isAdmin())
                        <h2 class="h5 mt-4">Quotation</h2>
                        @if ($booking->quotations->isNotEmpty())
                            <p class="mt-3"><a href="{{ route('admin.quotations.show', $booking) }}">Review quotation</a></p>
                        @endif
                        @forelse ($booking->quotations as $quotation)
                            @include('quotations.version', ['quotation' => $quotation])
                        @empty
                            <p class="mt-3">No quotation has been prepared for this visit.</p>
                        @endforelse
                    @endif

                    @if (auth()->id() === $booking->workerProfile->user_id)
                        @include('quotations.worker')
                    @endif

                    @if ($booking->latitude && $booking->longitude)
                        <h2 class="h5 mt-4">Home</h2>
                        <x-visit-map :locations="collect([$booking->location])" :selected="$booking->location_id" :latitude="$booking->latitude" :longitude="$booking->longitude" :editable="false" />
                    @endif

                    @if ($booking->photos->isNotEmpty())
                        <h2 class="h5">Photos of the problem</h2>
                        <div class="portfolio-grid mb-4">
                            @foreach ($booking->photos as $photo)
                                <img src="{{ route('bookings.photos.show', [$booking, $photo]) }}" alt="Photo of the problem">
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($customerCanReviewQuotation))
                        <p class="mt-4"><a href="{{ route('bookings.quotation.show', $booking) }}">Review the quotation</a></p>
                    @endif

                    @if (! empty($customerCanPayQuotation))
                        <p class="mt-3"><a href="{{ route('bookings.payments.index', $booking) }}">Submit a payment</a></p>
                    @endif

                    @if (auth()->id() === $booking->customer_id && $booking->status === 'pending' && $booking->payment === null)
                        <div class="payment-panel mt-4">
                            <h2 class="h5">Record payment</h2>
                            <p>This confirms the visit for {{ $booking->formattedPrice() }}. {{ config('brand.name') }} stores the payment in your account. No bank or card is charged.</p>
                            <form method="POST" action="{{ route('bookings.pay', $booking) }}">
                                @csrf
                                <x-button type="submit">Record {{ $booking->formattedPrice() }}</x-button>
                            </form>
                            <form class="mt-3" method="POST" action="{{ route('bookings.cancel', $booking) }}">
                                @csrf
                                <button class="btn btn-outline-secondary" type="submit">Cancel before payment</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
