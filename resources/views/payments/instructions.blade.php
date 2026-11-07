<div class="payment-panel">
    <h2 class="h5">Where to send the payment</h2>
    @forelse ($instructions as $instruction)
        <div @class(['mt-3' => ! $loop->first])>
            <h3 class="h6 mb-2">{{ $instruction['label'] }}</h3>
            @if ($instruction['is_placeholder'] || ($instruction['account_name'] === null && $instruction['account_number'] === null))
                <p class="mb-2">Account details have not been added yet.</p>
            @else
                <dl class="account-details">
                    @if ($instruction['account_name'])
                        <div>
                            <dt>Account name</dt>
                            <dd>{{ $instruction['account_name'] }}</dd>
                        </div>
                    @endif
                    @if ($instruction['bank_name'])
                        <div>
                            <dt>Bank</dt>
                            <dd>{{ $instruction['bank_name'] }}</dd>
                        </div>
                    @endif
                    @if ($instruction['account_number'])
                        <div>
                            <dt>{{ $instruction['method'] === 'gcash' ? 'GCash number' : 'Account number' }}</dt>
                            <dd>{{ $instruction['account_number'] }}</dd>
                        </div>
                    @endif
                </dl>
            @endif
            <p class="mb-2">{{ $instruction['instructions'] }}</p>
            @if ($instruction['has_qr'])
                <p class="form-text">A QR image is kept in private storage and is not published on a public link.</p>
            @endif
        </div>
    @empty
        <p class="mb-0">Payment methods are turned off, so a new claim cannot be submitted.</p>
    @endforelse
</div>
