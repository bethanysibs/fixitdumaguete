@php
    $row = $row ?? [];
    $type = old('items.'.$index.'.item_type', $row['item_type'] ?? 'labor');
    $description = old('items.'.$index.'.description', $row['description'] ?? '');
    $quantity = old('items.'.$index.'.quantity', $row['quantity'] ?? '');
    $unit = old('items.'.$index.'.unit', $row['unit'] ?? '');
    $unitPrice = old('items.'.$index.'.unit_price', $row['unit_price'] ?? '');
@endphp

<div class="admin-panel mt-3" data-quotation-item>
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Item</h3>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-remove-item>Remove</button>
    </div>
    <div class="mt-3">
        <label class="form-label" for="items-{{ $index }}-item-type">Type</label>
        <select class="form-select @error('items.'.$index.'.item_type') is-invalid @enderror" id="items-{{ $index }}-item-type" name="items[{{ $index }}][item_type]">
            @foreach (\App\Models\QuotationItem::typeOptions() as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('items.'.$index.'.item_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="mt-3">
        <label class="form-label" for="items-{{ $index }}-description">Description</label>
        <input class="form-control @error('items.'.$index.'.description') is-invalid @enderror" id="items-{{ $index }}-description" name="items[{{ $index }}][description]" value="{{ $description }}" required>
        @error('items.'.$index.'.description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <label class="form-label" for="items-{{ $index }}-quantity">Quantity</label>
            <input class="form-control @error('items.'.$index.'.quantity') is-invalid @enderror" id="items-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" value="{{ $quantity }}" inputmode="decimal" required>
            @error('items.'.$index.'.quantity')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="items-{{ $index }}-unit">Unit</label>
            <input class="form-control @error('items.'.$index.'.unit') is-invalid @enderror" id="items-{{ $index }}-unit" name="items[{{ $index }}][unit]" value="{{ $unit }}" placeholder="hour, piece, service" required>
            @error('items.'.$index.'.unit')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="items-{{ $index }}-unit-price">Unit price</label>
            <input class="form-control @error('items.'.$index.'.unit_price') is-invalid @enderror" id="items-{{ $index }}-unit-price" name="items[{{ $index }}][unit_price]" value="{{ $unitPrice }}" inputmode="decimal" required>
            @error('items.'.$index.'.unit_price')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <p class="form-text mb-0 mt-2">Line preview <span data-line-preview></span></p>
</div>
