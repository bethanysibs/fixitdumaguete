@props([
    'name',
    'label',
    'value' => null,
    'hint' => null,
    'rows' => 4,
])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $error ? $id.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($attributes->has('required'))
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden">required</span>
        @endif
    </label>
    <textarea
        name="{{ $name }}"
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'form-control'.($error ? ' is-invalid' : '')]) }}
    >{{ old($name, $value) }}</textarea>
    @if ($hint)
        <div id="{{ $id }}-hint" class="form-text">{{ $hint }}</div>
    @endif
    @if ($error)
        <div id="{{ $id }}-error" class="invalid-feedback">{{ $error }}</div>
    @endif
</div>
