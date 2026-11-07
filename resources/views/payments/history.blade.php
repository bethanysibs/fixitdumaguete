<h2 class="h5 mt-4">Receipts you sent</h2>
@if ($claims->isEmpty())
    <p>You have not sent a receipt yet.</p>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Method</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Sent</th>
                    <th scope="col">Status</th>
                    <th scope="col">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($claims as $claim)
                    <tr>
                        <td>{{ $claim->typeLabel() }}</td>
                        <td>{{ $claim->formattedAmount() }}</td>
                        <td>{{ $claim->methodLabel() }}</td>
                        <td>{{ $claim->payment_reference ?: 'Not entered' }}</td>
                        <td>{{ ($claim->submitted_at ?? $claim->created_at)?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                        <td>{{ $claim->customerStatusLabel() }}</td>
                        <td>
                            @foreach ($claim->proofs as $proof)
                                <a href="{{ route('bookings.payments.proof', [$booking, $claim, $proof]) }}">Download receipt</a>
                            @endforeach
                        </td>
                    </tr>
                    @if ($claim->rejection_reason)
                        <tr>
                            <td colspan="7">This receipt was not accepted: {{ $claim->rejection_reason }} You can send another receipt.</td>
                        </tr>
                    @endif
                    @if ($claim->notes)
                        <tr>
                            <td colspan="7">Note: {{ $claim->notes }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
