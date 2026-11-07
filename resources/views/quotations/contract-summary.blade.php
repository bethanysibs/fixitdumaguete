@php
    $audience = $audience ?? 'customer';
    $lines = $contract['accepted_lines'];
@endphp

<div class="payment-panel mt-4">
    <h2 class="h5">Contract summary</h2>
    <p class="form-text">The original advertised rate stays as it was when this visit was booked. Only the current accepted version of each additional quotation is added.</p>
    <dl class="account-details mb-0">
        <div>
            <dt>Original booked service</dt>
            <dd>{{ $booking->service->title }} · {{ $booking->service->category->name }}</dd>
        </div>
        <div>
            <dt>Original advertised rate</dt>
            <dd>{{ \App\Support\Money::format($contract['original_rate']) }}</dd>
        </div>
        @foreach ($lines as $line)
            <div>
                <dt>Accepted additional quotation #{{ $line['request_number'] }}</dt>
                <dd>{{ $line['title'] }} · {{ \App\Support\Money::format($line['amount']) }}</dd>
            </div>
        @endforeach
        <div>
            <dt>Accepted additional work</dt>
            <dd>{{ \App\Support\Money::format($contract['accepted_additional']) }}</dd>
        </div>
        <div>
            <dt>Total contract amount</dt>
            <dd>{{ \App\Support\Money::format($contract['contract_amount']) }}</dd>
        </div>
        <div>
            <dt>Verified payments</dt>
            <dd>{{ \App\Support\Money::format($contract['verified_payments']) }}</dd>
        </div>
        <div>
            <dt>Waiting for review</dt>
            <dd>{{ \App\Support\Money::format($contract['waiting_review']) }}</dd>
        </div>
        <div>
            <dt>Remaining amount payable</dt>
            <dd>{{ \App\Support\Money::format($contract['remaining_payable']) }}</dd>
        </div>
    </dl>

    @if ($audience !== 'customer')
        <div class="table-responsive admin-table mt-3">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Additional quotation</th>
                        <th>Current version</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>In the contract</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contract['requests'] as $request)
                        <tr>
                            <td>#{{ $request['request_number'] }} · {{ $request['title'] }}</td>
                            <td>{{ $request['current_version'] ? 'Version '.$request['current_version'] : 'None' }}</td>
                            <td>{{ $request['status'] === 'canceled' ? 'Canceled' : ($request['current_status'] ?? 'None') }}</td>
                            <td>{{ $request['current_amount'] ? \App\Support\Money::format($request['current_amount']) : '—' }}</td>
                            <td>{{ $request['counts_toward_contract'] ? \App\Support\Money::format($request['accepted_amount']) : 'Not included' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No additional quotation has been prepared.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($lines === [])
        <p class="form-text mt-3 mb-0">No additional work has been accepted.</p>
    @endif

    @if (! empty($showPaymentLimitation))
        <p class="form-text mt-3 mb-0">A payment claim is still recorded against one accepted quotation version. It is not yet applied to this combined contract amount.</p>
    @endif
</div>
