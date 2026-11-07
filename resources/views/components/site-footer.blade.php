<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <x-brand-logo />
                <p class="mt-3 mb-0">{{ config('brand.tagline') }}</p>
            </div>
            <div class="col-sm-6 col-lg-3">
                <h2>Areas</h2>
                <ul>
                    @foreach (config('brand.locations') as $location)
                        <li>{{ $location['name'] }}, {{ $location['province'] }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="col-sm-6 col-lg-4">
                <h2>Explore</h2>
                <ul>
                    @foreach (config('brand.nav') as $item)
                        @php
                            $href = isset($item['route'])
                                ? route($item['route'])
                                : route('home').'#'.$item['fragment'];
                        @endphp
                        <li><a href="{{ $href }}">{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <p class="small mt-4 mb-0">© {{ now()->year }} {{ config('brand.name') }}. A local home services marketplace for Negros Oriental.</p>
    </div>
</footer>
