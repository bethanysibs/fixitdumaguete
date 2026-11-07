@php
    $rule = match ($depositRule) {
        'percentage' => 'Percentage of the accepted quotation',
        'fixed' => 'Fixed amount',
        'disabled' => 'Deposit requirement turned off',
        'unconfigured' => 'No deposit requirement saved',
        default => null,
    };
@endphp

<div class="payment-panel">
    <h2 class="h5">Payment summary</h2>
    @if ($quotation === null)
        <p class="mb-0">Accept the current quotation before sending a payment. A claim cannot use an older version.</p>
    @else
        <dl class="account-details mb-0">
            <div>
                <dt>Accepted quotation</dt>
                <dd>Version {{ $quotation->version_number }}</dd>
            </div>
            <div>
                <dt>Quotation total</dt>
                <dd>{{ $acceptedTotal }}</dd>
            </div>
            @if ($rule)
                <div>
                    <dt>Deposit rule</dt>
                    <dd>{{ $rule }}</dd>
                </div>
            @endif
            <div>
                <dt>Required deposit</dt>
                <dd>{{ $requiredDeposit }}</dd>
            </div>
            <div>
                <dt>Verified payments</dt>
                <dd>{{ $verifiedPayments }}</dd>
            </div>
            <div>
                <dt>Waiting for review</dt>
                <dd>{{ $openClaims }}</dd>
            </div>
            <div>
                <dt>Deposit still due</dt>
                <dd>{{ $depositOutstanding }}</dd>
            </div>
            <div>
                <dt>Remaining balance</dt>
                <dd>{{ $balanceOutstanding }}</dd>
            </div>
        </dl>
        <p class="form-text mt-3 mb-0">A submitted receipt is a claim for review. It does not confirm the payment or change the booked price.</p>
    @endif
</div>
