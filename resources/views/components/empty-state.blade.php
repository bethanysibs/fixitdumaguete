@props([
    'title',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }} role="status">
    <h2 class="h5">{{ $title }}</h2>
    @if ($message)
        <p class="mb-0">{{ $message }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
