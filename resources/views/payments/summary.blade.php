@php
    $extras = $contract['accepted_lines'];
@endphp

<div class="payment-panel">
    <h2 class="h5">What you pay</h2>
    <dl class="account-details mb-0">
        <div>
            <dt>Booked service</dt>
            <dd>{{ \App\Support\Money::format($contract['original_rate']) }}</dd>
        </div>
        @foreach ($extras as $extra)
            <div>
                <dt>{{ count($extras) > 1 ? 'Extra work '.$extra['request_number'] : 'Extra work' }}</dt>
                <dd>
                    @if (! preg_match('/^Additional quotation \d+$/', $extra['title']))
                        {{ $extra['title'] }} ·
                    @endif
                    {{ \App\Support\Money::format($extra['amount']) }}
                </dd>
            </div>
        @endforeach
        <div>
            <dt>Total</dt>
            <dd class="fw-semibold">{{ \App\Support\Money::format($contract['contract_amount']) }}</dd>
        </div>
        <div>
            <dt>Paid</dt>
            <dd>{{ \App\Support\Money::format($contract['verified_payments']) }}</dd>
        </div>
        @if ($contract['waiting_cents'] > 0)
            <div>
                <dt>Receipt waiting to be checked</dt>
                <dd>{{ \App\Support\Money::format($contract['waiting_review']) }}</dd>
            </div>
        @endif
        <div>
            <dt>Still to pay</dt>
            <dd class="fw-semibold">{{ \App\Support\Money::format($contract['remaining_payable']) }}</dd>
        </div>
        @if (! empty($depositDue))
            <div>
                <dt>Send with the first receipt</dt>
                <dd>{{ $depositDue }}</dd>
            </div>
        @endif
    </dl>
    <p class="form-text mt-3 mb-0">A receipt is checked before it counts as paid.</p>
</div>
