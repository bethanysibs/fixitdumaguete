@php
    $requests = $booking->quotationRequests;
    $canPrepare = $booking->allowsNewQuotation();
    $canEdit = $booking->allowsQuotationEdits();
@endphp

<h2 class="h5 mt-4">Quotation</h2>

@if ($booking->status === 'canceled')
    <div class="alert alert-info mt-3" role="status">This visit is canceled, so the quotation cannot be changed.</div>
@elseif ($booking->status === 'completed')
    <div class="alert alert-info mt-3" role="status">This visit is done, so a new quotation cannot be prepared or submitted.</div>
@endif

@foreach ($requests as $request)
    @php
        $current = $request->quotations->firstWhere('is_current', true);
        $draft = $current && $current->status === 'draft' ? $current : null;
        $editableDraft = $draft && $canEdit;
        $savedRows = $draft
            ? $draft->items->map(fn ($item) => [
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
            ])->all()
            : [];
        $rows = old('quotation_request_id') == $request->id ? old('items', $savedRows) : $savedRows;
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

    <h3 class="h6 mt-4">Additional quotation #{{ $request->request_number }} · {{ $request->title }}</h3>
    @if ($request->description)
        <p>{{ $request->description }}</p>
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
    @elseif ($current && $current->status === 'approved')
        <div class="alert alert-info mt-3" role="status">
            The customer accepted this quotation
            @if ($current->customer_approved_at)
                on {{ $current->customer_approved_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
            @endif
            . It cannot be edited.
        </div>
    @elseif ($current && $current->status === 'customer_rejected')
        <div class="alert alert-info mt-3" role="status">
            The customer declined this quotation
            @if ($current->customer_rejected_at)
                on {{ $current->customer_rejected_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
            @endif
            .
            @if ($current->customer_rejection_reason)
                Reason: {{ $current->customer_rejection_reason }}
            @endif
        </div>
    @elseif ($current && $current->status === 'rejected')
        <div class="alert alert-info mt-3" role="status">
            An administrator rejected this quotation.
            @if ($current->rejection_reason)
                Reason: {{ $current->rejection_reason }}
            @endif
        </div>
    @endif

    @foreach ($request->quotations as $quotation)
        @if (! ($editableDraft && $quotation->is($draft)))
            @include('quotations.version', ['quotation' => $quotation])
        @endif
    @endforeach

    @if ($editableDraft)
        @include('quotations.version', ['quotation' => $draft])
        @if ($canPrepare)
            <p class="mt-3"><a href="{{ route('jobs.quotation.confirm', [$booking, 'quotation_request_id' => $request->id]) }}">Review and submit</a></p>
        @endif

        <form class="mt-3" method="POST" action="{{ route('jobs.quotation.update', $booking) }}" data-quotation-composer>
            @csrf
            @method('PUT')
            <input type="hidden" name="quotation_request_id" value="{{ $request->id }}">
            <p class="form-text">Saving updates this draft. It does not create a new version. The preview is only a guide.</p>
            <div data-quotation-items>
                @foreach ($rows as $index => $row)
                    @include('quotations.item-fields', ['index' => $index, 'row' => $row])
                @endforeach
            </div>
            <button class="btn btn-outline-secondary" type="button" data-add-quotation-item>Add item</button>
            <p class="form-text mt-3" data-quotation-preview>Preview only. Saving calculates the total on the server.</p>
            <x-form-textarea name="notes" label="Notes" :value="$draft->notes" hint="Optional note for this quotation." rows="3" id="quotation-notes-{{ $request->id }}" />
            <x-button type="submit">Save draft</x-button>
            <template>
                @include('quotations.item-fields', ['index' => '__INDEX__', 'row' => [
                    'item_type' => 'labor',
                    'description' => '',
                    'quantity' => '',
                    'unit' => '',
                    'unit_price' => '',
                ]])
            </template>
        </form>
    @endif

    @if ($current && $current->canBeRevisedByWorker() && $canPrepare)
        <form class="mt-3" method="POST" action="{{ route('jobs.quotation.revise', $booking) }}">
            @csrf
            <input type="hidden" name="quotation_request_id" value="{{ $request->id }}">
            @if ($current->status === 'rejected')
                <p>A revision keeps this rejected quotation and opens a new draft.</p>
            @elseif ($current->status === 'customer_rejected')
                <p>A revision keeps this declined quotation and opens a new draft.</p>
            @else
                <p>A revision keeps version {{ $current->version_number }} as it was submitted and opens a new draft.</p>
            @endif
            <x-button type="submit" variant="secondary">Prepare a revision</x-button>
        </form>
    @endif
@endforeach

@if ($canPrepare)
    @if ($requests->isEmpty())
        <p class="mt-3">Prepare a quotation for this visit. The customer does not see it yet.</p>
    @else
        <h3 class="h6 mt-4">Another additional quotation</h3>
        <p>This starts a separate charge. It does not change an accepted quotation or the original booked price.</p>
    @endif

    <form id="quotation-form" class="mt-3" method="POST" action="{{ route('jobs.quotation.store', $booking) }}" data-quotation-composer>
        @csrf
        <p class="form-text">Saving creates a new additional quotation. The preview is only a guide.</p>
        @if ($errors->first('items') && old('quotation_request_id') === null)
            <div class="invalid-feedback d-block">{{ $errors->first('items') }}</div>
        @endif
        <x-form-input name="title" label="Title" :value="old('title')" hint="Optional. A name is added when this is left blank." id="additional-quotation-title" />
        <x-form-textarea name="description" label="Description" :value="old('description')" hint="Optional description of this additional work." rows="3" id="additional-quotation-description" />
        <div data-quotation-items>
            @php
                $newRows = old('quotation_request_id') === null ? old('items', []) : [];
                if (! is_array($newRows) || $newRows === []) {
                    $newRows = [[
                        'item_type' => 'labor',
                        'description' => '',
                        'quantity' => '',
                        'unit' => '',
                        'unit_price' => '',
                    ]];
                }
            @endphp
            @foreach ($newRows as $index => $row)
                @include('quotations.item-fields', ['index' => $index, 'row' => $row])
            @endforeach
        </div>
        <button class="btn btn-outline-secondary" id="add-quotation-item" type="button" data-add-quotation-item>Add item</button>
        <p class="form-text mt-3" id="quotation-preview" data-quotation-preview>Preview only. Saving calculates the total on the server.</p>
        <x-form-textarea name="notes" label="Notes" :value="old('notes')" hint="Optional note for this quotation." rows="3" id="additional-quotation-notes" />
        <x-button type="submit">{{ $requests->isEmpty() ? 'Save draft' : 'Start additional quotation' }}</x-button>
        <template id="quotation-item-template">
            @include('quotations.item-fields', ['index' => '__INDEX__', 'row' => [
                'item_type' => 'labor',
                'description' => '',
                'quantity' => '',
                'unit' => '',
                'unit_price' => '',
            ]])
        </template>
    </form>
@endif

@include('quotations.item-script')
