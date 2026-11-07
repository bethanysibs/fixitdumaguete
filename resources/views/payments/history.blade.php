<h2 class="h5 mt-4">Payment claims</h2>
@if ($claims->isEmpty())
    <p>No payment claim has been submitted for this visit.</p>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Method</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Status</th>
                    <th scope="col">Review</th>
                    <th scope="col">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($claims as $claim)
                    <tr>
                        <td>{{ $claim->typeLabel() }} · version {{ $claim->quotation_version }}</td>
                        <td>{{ $claim->formattedAmount() }}</td>
                        <td>{{ $claim->methodLabel() }}</td>
                        <td>{{ $claim->payment_reference ?: 'Not entered' }}</td>
                        <td>{{ ($claim->submitted_at ?? $claim->created_at)?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                        <td>{{ $claim->customerStatusLabel() }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'text-bg-warning' => $claim->verification_status === 'pending_review',
                                'text-bg-danger' => $claim->verification_status === 'rejected' || $claim->status === 'rejected',
                                'text-bg-success' => $claim->verification_status === 'verified',
                                'text-bg-secondary' => ! in_array($claim->verification_status, ['pending_review', 'rejected', 'verified'], true) && $claim->status !== 'rejected',
                            ])>{{ $claim->customerVerificationLabel() }}</span>
                        </td>
                        <td>
                            @foreach ($claim->proofs as $proof)
                                <a href="{{ route('bookings.payments.proof', [$booking, $claim, $proof]) }}">Download receipt</a>
                            @endforeach
                        </td>
                    </tr>
                    @if ($claim->rejection_reason)
                        <tr>
                            <td colspan="8">
                                <strong>Rejection reason:</strong> {{ $claim->rejection_reason }}
                            </td>
                        </tr>
                    @endif
                    @if ($claim->notes)
                        <tr>
                            <td colspan="8">Note: {{ $claim->notes }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
