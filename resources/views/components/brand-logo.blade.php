<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'brand-logo']) }}>
    <img src="{{ asset(config('brand.logo')) }}" alt="" width="36" height="36">
    <span>{{ config('brand.name') }}</span>
</a>
