<section class="section-block services-section" id="services" aria-labelledby="services-title">
    <div class="container">
        <div class="row section-heading">
            <div class="col-lg-7">
                <h2 id="services-title">Services the marketplace will offer</h2>
                <p class="lede mb-0">Choose a service to see the professionals who offer that job. A category stays on this list only while it is part of the catalog.</p>
            </div>
        </div>
        <div class="row g-4">
            @foreach ($catalog as $service)
                <div class="col-sm-6 col-lg-4">
                    @if ($service['url'])
                        <a class="service-card" href="{{ $service['url'] }}">
                            <span class="service-icon" aria-hidden="true"><i class="bi {{ $service['icon'] }}"></i></span>
                            <h3>{{ $service['name'] }}</h3>
                            <p>{{ $service['summary'] }}</p>
                            <span class="service-card-link">See workers</span>
                        </a>
                    @else
                        <article class="service-card">
                            <span class="service-icon" aria-hidden="true"><i class="bi {{ $service['icon'] }}"></i></span>
                            <h3>{{ $service['name'] }}</h3>
                            <p class="mb-0">{{ $service['summary'] }}</p>
                        </article>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
