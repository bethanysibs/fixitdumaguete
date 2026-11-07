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

@if ($current && $current->status === 'submitted')
    <div class="alert alert-info mt-3" role="status">
        This quotation was submitted for admin review
        @if ($current->submitted_at)
            on {{ $current->submitted_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
        @endif
        and can no longer be edited.
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

@if ($current && $current->status === 'submitted' && $canPrepare)
    <form class="mt-3" method="POST" action="{{ route('jobs.quotation.revise', $booking) }}">
        @csrf
        <p>A revision keeps version {{ $current->version_number }} as it was submitted and opens a new draft.</p>
        <x-button type="submit" variant="secondary">Prepare a revision</x-button>
    </form>
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.getElementById('quotation-form');
            var template = document.getElementById('quotation-item-template');

            if (!root || !template || !window.jQuery) {
                return;
            }

            var $ = window.jQuery;
            var list = $('#quotation-items');

            function cents(value) {
                var match = String(value == null ? '' : value).trim().match(/^(\d{1,10})(?:\.(\d{1,2}))?$/);

                if (!match) {
                    return null;
                }

                return (parseInt(match[1], 10) * 100) + parseInt((match[2] || '').padEnd(2, '0'), 10);
            }

            function format(amount) {
                var whole = Math.floor(amount / 100);
                var fraction = String(amount % 100).padStart(2, '0');

                return '₱' + whole.toLocaleString('en-US') + '.' + fraction;
            }

            function preview() {
                var total = 0;
                var valid = true;

                list.children('[data-quotation-item]').each(function () {
                    var quantity = cents($(this).find('[name$="[quantity]"]').val());
                    var price = cents($(this).find('[name$="[unit_price]"]').val());
                    var line = quantity !== null && price !== null && quantity > 0 && price >= 0
                        ? Math.floor((quantity * price) / 100)
                        : null;

                    $(this).find('[data-line-preview]').text(line === null ? '' : format(line));

                    if (line === null) {
                        valid = false;
                        return;
                    }

                    total += line;
                });

                $('#quotation-preview').text(valid
                    ? 'Preview ' + format(total) + '. Saving calculates the total on the server.'
                    : 'Preview only. Saving calculates the total on the server.');
            }

            function reindex() {
                list.children('[data-quotation-item]').each(function (index) {
                    $(this).find('[name]').each(function () {
                        this.name = this.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    });
                    $(this).find('[id]').each(function () {
                        this.id = this.id.replace(/items-\d+-/, 'items-' + index + '-');
                    });
                    $(this).find('label[for]').each(function () {
                        this.htmlFor = this.htmlFor.replace(/items-\d+-/, 'items-' + index + '-');
                    });
                });

                list.find('[data-remove-item]').prop('disabled', list.children().length < 2);
                preview();
            }

            $('#add-quotation-item').on('click', function () {
                list.append(template.innerHTML.replaceAll('__INDEX__', String(list.children().length)));
                reindex();
            });

            list.on('click', '[data-remove-item]', function () {
                if (list.children().length < 2) {
                    return;
                }

                $(this).closest('[data-quotation-item]').remove();
                reindex();
            });

            list.on('input change', 'input, select', preview);
            reindex();
        });
    </script>
@endpush
