@props([
    'average' => null,
    'count' => 0,
])

@php
    $count = (int) $count;
    $numeric = $count > 0 ? (float) $average : 0.0;
    $steps = (int) round($numeric * 2);
    $display = number_format($numeric, 1);
    $label = $count === 0
        ? 'No ratings yet'
        : $display.' out of 5, '.$count.' '.($count === 1 ? 'rating' : 'ratings');
@endphp

<span {{ $attributes->merge(['class' => 'star-rating']) }} role="img" aria-label="{{ $label }}">
    @for ($star = 1; $star <= 5; $star++)
        @if ($steps >= $star * 2)
            <i class="bi bi-star-fill" aria-hidden="true"></i>
        @elseif ($steps === ($star * 2) - 1)
            <i class="bi bi-star-half" aria-hidden="true"></i>
        @else
            <i class="bi bi-star" aria-hidden="true"></i>
        @endif
    @endfor
    <span class="star-rating-label">{{ $label }}</span>
</span>
