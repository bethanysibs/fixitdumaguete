@php
    $current = $booking->quotations->firstWhere('is_current', true);
    $draft = $current && $current->status === 'draft' ? $current : null;
    $canPrepare = $booking->allowsNewQuotation();
    $editableDraft = $draft && $booking->allowsQuotationEdits();
    $savedRows = $draft
        ? $draft->items->map(fn ($item) => [
            'item_type' => $item->item_type,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
        ])->all()
        : [];
    $rows = old('items', $savedRows);
    if (! is_array($rows) || $rows === []) {
        $rows = [[
            'item_type' => 'labor',
            'description' => '',
            'quantity' => '',
            'unit' => '',
            'unit_price' => '',
        ]];
    }
@endphp

<h2 class="h5 mt-4">Quotation</h2>

@if ($booking->status === 'canceled')
    <div class="alert alert-info mt-3" role="status">This visit is canceled, so the quotation cannot be changed.</div>
@elseif ($booking->status === 'completed')
    <div class="alert alert-info mt-3" role="status">This visit is done, so a new quotation cannot be prepared or submitted.</div>
@endif

@if ($current && $current->status === 'submitted' && $current->is_admin_adjustment)
    <div class="alert alert-info mt-3" role="status">An administrator adjusted this quotation. It is under review and cannot be edited.</div>
@elseif ($current && $current->status === 'submitted')
    <div class="alert alert-info mt-3" role="status">
        This quotation is under review. It was submitted
        @if ($current->submitted_at)
            on {{ $current->submitted_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
        @endif
        and can no longer be edited.
    </div>
@elseif ($current && $current->status === 'admin_approved')
    <div class="alert alert-info mt-3" role="status">An administrator approved this quotation for customer review. It cannot be edited.</div>
@elseif ($current && $current->status === 'rejected')
    <div class="alert alert-info mt-3" role="status">
        An administrator rejected this quotation.
        @if ($current->rejection_reason)
            Reason: {{ $current->rejection_reason }}
        @endif
    </div>
@elseif (! $draft && $canPrepare)
    <p class="mt-3">Prepare a quotation for this visit. The customer does not see it yet.</p>
@endif

@foreach ($booking->quotations as $quotation)
    @if (! ($editableDraft && $quotation->is($draft)))
        @include('quotations.version', ['quotation' => $quotation])
    @endif
@endforeach

@if ($editableDraft)
    @include('quotations.version', ['quotation' => $draft])
    @if ($canPrepare)
        <p class="mt-3"><a href="{{ route('jobs.quotation.confirm', $booking) }}">Review and submit</a></p>
    @endif
@endif

@if ($editableDraft || (! $current && $canPrepare))
    <form id="quotation-form" class="mt-3" method="POST" action="{{ $draft ? route('jobs.quotation.update', $booking) : route('jobs.quotation.store', $booking) }}">
        @csrf
        @if ($draft)
            @method('PUT')
        @endif

        <p class="form-text">Saving updates this draft. It does not create a new version. The preview is only a guide.</p>
        @if ($errors->first('items'))
            <div class="invalid-feedback d-block">{{ $errors->first('items') }}</div>
        @endif

        <div id="quotation-items">
            @foreach ($rows as $index => $row)
                @include('quotations.item-fields', ['index' => $index, 'row' => $row])
            @endforeach
        </div>

        <button class="btn btn-outline-secondary" id="add-quotation-item" type="button">Add item</button>
        <p class="form-text mt-3" id="quotation-preview">Preview only. Saving calculates the total on the server.</p>

        <x-form-textarea name="notes" label="Notes" :value="$draft?->notes" hint="Optional note for this quotation." rows="3" />

        <x-button type="submit">Save draft</x-button>
    </form>

    <template id="quotation-item-template">
        @include('quotations.item-fields', ['index' => '__INDEX__', 'row' => [
            'item_type' => 'labor',
            'description' => '',
            'quantity' => '',
            'unit' => '',
            'unit_price' => '',
        ]])
    </template>
@endif

@if ($current && $current->canBeRevisedByWorker() && $canPrepare)
    <form class="mt-3" method="POST" action="{{ route('jobs.quotation.revise', $booking) }}">
        @csrf
        @if ($current->status === 'rejected')
            <p>A revision keeps this rejected quotation and opens a new draft.</p>
        @else
            <p>A revision keeps version {{ $current->version_number }} as it was submitted and opens a new draft.</p>
        @endif
        <x-button type="submit" variant="secondary">Prepare a revision</x-button>
    </form>
@endif

@include('quotations.item-script')
