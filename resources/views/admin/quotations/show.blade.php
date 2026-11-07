@php
    $current = $booking->quotations->firstWhere('is_current', true);
    $canReview = $booking->allowsNewQuotation();
    $canAdjust = $canReview && $current && in_array($current->status, ['submitted', 'admin_approved', 'approved'], true);
    $canDecide = $canReview && $current && $current->status === 'submitted';
    $savedRows = $current
        ? $current->items->map(fn ($item) => [
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

@extends('layouts.app')

@section('title', 'Review quotation | '.config('brand.name'))
@section('meta_description', 'Review a worker quotation before the customer sees it.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Bookings', 'url' => route('admin.bookings.index')],
                ['label' => 'Review quotation'],
            ]" />

            <h1 class="mt-3">Review quotation</h1>
            <p class="lede">{{ $booking->workerProfile->user->name }} · {{ $booking->service->category->name }} for {{ $booking->customer->name }}</p>

            <dl class="account-details">
                <div>
                    <dt>Visit</dt>
                    <dd><span class="status-pill status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></dd>
                </div>
                <div>
                    <dt>When</dt>
                    <dd>{{ $booking->scheduled_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt>Booked price</dt>
                    <dd>{{ $booking->formattedPrice() }}</dd>
                </div>
                <div>
                    <dt>Address</dt>
                    <dd>{{ $booking->address ?: 'Not recorded' }}</dd>
                </div>
            </dl>

            @if ($booking->status === 'canceled')
                <div class="alert alert-info mt-3" role="status">This visit is canceled, so the quotation cannot be changed.</div>
            @elseif ($booking->status === 'completed')
                <div class="alert alert-info mt-3" role="status">This visit is done, so the quotation cannot be adjusted or approved.</div>
            @elseif ($current && $current->status === 'draft')
                <div class="alert alert-info mt-3" role="status">The worker has not submitted this draft.</div>
            @elseif ($current && $current->status === 'admin_approved')
                <div class="alert alert-info mt-3" role="status">This quotation is approved for customer review. Changing the price or the customer note saves a new version that needs approval again.</div>
            @elseif ($current && $current->status === 'approved')
                <div class="alert alert-info mt-3" role="status">The customer accepted this quotation. Changing the price or the customer note saves a new version. The accepted version stays unchanged.</div>
            @elseif ($current && $current->status === 'customer_rejected')
                <div class="alert alert-info mt-3" role="status">The customer declined this quotation. The worker can prepare a revision.</div>
            @elseif (! $current)
                <p class="mt-3">No quotation has been prepared for this visit.</p>
            @endif

            @foreach ($booking->quotations as $quotation)
                @include('quotations.version', ['quotation' => $quotation, 'showAdminNotes' => true])
            @endforeach

            @if ($canAdjust)
                <h2 class="h5 mt-4">Adjust this quotation</h2>
                <form id="quotation-form" class="mt-3" method="POST" action="{{ route('admin.quotations.update', $booking) }}">
                    @csrf
                    @method('PUT')

                    <p class="form-text">Changing a line item or the customer note saves a new version and keeps the earlier quotation. An internal note alone stays on this version. The preview is only a guide, and saving does not approve the quotation.</p>
                    @if ($errors->first('items'))
                        <div class="invalid-feedback d-block">{{ $errors->first('items') }}</div>
                    @endif
                    @if ($errors->first('quotation'))
                        <div class="invalid-feedback d-block">{{ $errors->first('quotation') }}</div>
                    @endif

                    <div id="quotation-items">
                        @foreach ($rows as $index => $row)
                            @include('quotations.item-fields', ['index' => $index, 'row' => $row])
                        @endforeach
                    </div>

                    <button class="btn btn-outline-secondary" id="add-quotation-item" type="button">Add item</button>
                    <p class="form-text mt-3" id="quotation-preview">Preview only. Saving calculates the total on the server.</p>

                    <x-form-textarea name="notes" label="Note for the customer" :value="$current->notes" hint="The customer will be able to read this note after customer review is available." rows="3" />
                    <x-form-textarea name="admin_notes" label="Internal note" :value="$current->admin_notes" hint="Only administrators can read this note." rows="3" />

                    <x-button type="submit">Save adjustment</x-button>
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

            @if ($canDecide)
                <h2 class="h5 mt-4">Decision</h2>
                <p class="mt-3"><a href="{{ route('admin.quotations.confirm', $booking) }}">Approve for customer review</a></p>

                <form class="mt-3" method="POST" action="{{ route('admin.quotations.reject', $booking) }}">
                    @csrf
                    <x-form-textarea name="rejection_reason" label="Rejection reason" hint="The assigned worker can read this reason." rows="3" required />
                    <x-form-textarea id="rejection-admin-notes" name="admin_notes" label="Internal note" :value="$current->admin_notes" hint="Only administrators can read this note." rows="3" />
                    <x-button type="submit" variant="secondary">Reject quotation</x-button>
                </form>
            @endif

            <p class="mt-4"><a href="{{ route('bookings.show', $booking) }}">Return to the visit</a></p>
        </div>
    </section>
@endsection

@include('quotations.item-script')
