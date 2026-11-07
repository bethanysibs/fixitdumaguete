@extends('layouts.app')

@section('title', 'Check payment | '.config('brand.name'))
@section('meta_description', 'Check one customer payment receipt.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Payments', 'url' => route('admin.payments.index')],
                ['label' => 'Booking '.$payment->booking_id],
            ]" />

            <h1 class="mt-3">Check this receipt</h1>
            <p class="lede">{{ $booking->customer->name }} · {{ $booking->service->title }}</p>

            <div class="row g-4 mt-1">
                <div class="col-lg-7">
                    <div class="payment-panel">
                        <h2 class="h5">Receipt</h2>
                        <dl class="account-details mb-0">
                            <div>
                                <dt>Amount</dt>
                                <dd>{{ $payment->formattedAmount() }}</dd>
                            </div>
                            <div>
                                <dt>Method</dt>
                                <dd>{{ $payment->methodLabel() }}</dd>
                            </div>
                            <div>
                                <dt>Reference</dt>
                                <dd>{{ $payment->payment_reference ?: 'Not entered' }}</dd>
                            </div>
                            <div>
                                <dt>Note</dt>
                                <dd>{{ $payment->notes ?: 'None' }}</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd>{{ $payment->customerStatusLabel() }}</dd>
                            </div>
                            @foreach ($payment->proofs as $proof)
                                <div>
                                    <dt>Receipt file</dt>
                                    <dd><a href="{{ route('bookings.payments.proof', [$booking, $payment, $proof]) }}">Open receipt</a></dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    @include('quotations.contract-summary', ['audience' => 'admin'])

                    <div class="payment-panel mt-4">
                        <h2 class="h5">What is still unpaid</h2>
                        <dl class="account-details mb-0">
                            <div>
                                <dt>Paid</dt>
                                <dd>{{ \App\Support\Money::format(\App\Support\Money::fromCents($summary['verified_cents'])) }}</dd>
                            </div>
                            <div>
                                <dt>Waiting to be checked</dt>
                                <dd>{{ \App\Support\Money::format(\App\Support\Money::fromCents($summary['pending_cents'])) }}</dd>
                            </div>
                            <div>
                                <dt>Still to pay</dt>
                                <dd>{{ \App\Support\Money::format(\App\Support\Money::fromCents($summary['balance_outstanding_cents'])) }}</dd>
                            </div>
                        </dl>
                        <p class="form-text mt-3 mb-0">Only a receipt you mark as paid reduces what the customer still owes.</p>
                    </div>
                </div>
                <div class="col-lg-5">
                    @if ($payment->status === 'submitted' && $payment->verification_status === 'pending_review')
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" onsubmit="return confirm('Mark this receipt as paid?');">
                            @csrf
                            <x-button type="submit">Mark as paid</x-button>
                        </form>
                        <form class="mt-4" method="POST" action="{{ route('admin.payments.reject', $payment) }}" onsubmit="return confirm('Reject this receipt? The customer can send another one.');">
                            @csrf
                            <x-form-textarea name="rejection_reason" label="Reason" rows="3" hint="Tell the customer why this receipt was not accepted." required />
                            <button class="btn btn-outline-danger" type="submit">Do not accept</button>
                        </form>
                    @else
                        <p>This receipt has already been checked.</p>
                        @if ($payment->verifier)
                            <p>Marked paid by {{ $payment->verifier->name }} on {{ $payment->verified_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.</p>
                        @endif
                        @if ($payment->rejection_reason)
                            <p>Not accepted: {{ $payment->rejection_reason }}</p>
                        @endif
                    @endif

                    <h2 class="h5 mt-4">Earlier checks</h2>
                    @if ($payment->logs->isEmpty())
                        <p>No earlier checks.</p>
                    @else
                        <ul class="list-unstyled">
                            @foreach ($payment->logs as $log)
                                <li class="mb-2">
                                    {{ $log->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    · {{ $log->user?->name ?? 'System' }}
                                    · {{ $log->description }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
