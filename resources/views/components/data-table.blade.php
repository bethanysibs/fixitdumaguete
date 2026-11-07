@props([
    'caption' => null,
])

<div class="table-responsive">
    <table {{ $attributes->merge(['class' => 'table table-striped align-middle mb-0']) }}>
        @if ($caption)
            <caption>{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
