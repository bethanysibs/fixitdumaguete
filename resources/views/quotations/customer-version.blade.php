<article class="admin-panel mt-3">
    <p class="eyebrow mb-2">
        <span class="status-pill">Version {{ $quotation->version_number }}</span>
        <span class="status-pill">{{ $quotation->customerStatusLabel() }}</span>
        @if ($quotation->is_current)
            <span class="status-pill">Current</span>
        @endif
    </p>
    <dl class="account-details">
        <div>
            <dt>Quotation total</dt>
            <dd>{{ $quotation->formattedTotal() }}</dd>
        </div>
        @if ($quotation->customer_approved_at)
            <div>
                <dt>Your decision</dt>
                <dd>Accepted on {{ $quotation->customer_approved_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
            </div>
        @endif
        @if ($quotation->customer_rejected_at)
            <div>
                <dt>Your decision</dt>
                <dd>Declined on {{ $quotation->customer_rejected_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
            </div>
        @endif
        @if ($quotation->customer_rejection_reason)
            <div>
                <dt>Your reason</dt>
                <dd>{{ $quotation->customer_rejection_reason }}</dd>
            </div>
        @endif
        @if ($quotation->notes)
            <div>
                <dt>Note</dt>
                <dd>{{ $quotation->notes }}</dd>
            </div>
        @endif
    </dl>
    <div class="table-responsive admin-table mt-3">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Unit price</th>
                    <th>Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->typeLabel() }}</td>
                        <td>{{ $item->quantity }} {{ $item->unit }}</td>
                        <td>{{ $item->formattedUnitPrice() }}</td>
                        <td>{{ $item->formattedLineTotal() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</article>
