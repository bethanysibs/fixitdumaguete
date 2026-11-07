@props([
    'paginator',
])

<div {{ $attributes->merge(['class' => 'pagination-wrap']) }}>
    {{ $paginator->links() }}
</div>
