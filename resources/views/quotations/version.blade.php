<article class="admin-panel mt-3">
    <p class="eyebrow mb-2">
        <span class="status-pill">Version {{ $quotation->version_number }}</span>
        <span class="status-pill">{{ $quotation->statusLabel() }}</span>
        @if ($quotation->is_current)
            <span class="status-pill">Current</span>
        @endif
    </p>
    <dl class="account-details">
        <div>
            <dt>Subtotal</dt>
            <dd>{{ $quotation->formattedSubtotal() }}</dd>
        </div>
        <div>
            <dt>Total</dt>
            <dd>{{ $quotation->formattedTotal() }}</dd>
        </div>
        <div>
            <dt>Prepared</dt>
            <dd>
                {{ $quotation->created_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                @if ($quotation->creator)
                    · {{ $quotation->creator->name }}
                @endif
            </dd>
        </div>
        @if ($quotation->submitted_at)
            <div>
                <dt>Submitted</dt>
                <dd>{{ $quotation->submitted_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
            </div>
        @endif
        @if ($quotation->notes)
            <div>
                <dt>Notes</dt>
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
