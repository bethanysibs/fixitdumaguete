<section class="section-block" id="locations" aria-labelledby="locations-title">
    <div class="container">
        <div class="row section-heading">
            <div class="col-lg-8">
                <h2 id="locations-title">Where FixItDumaguete starts</h2>
                <p class="lede mb-0">The first municipalities are in Negros Oriental. The location records will be expandable later, so adding another city does not require a new database design.</p>
            </div>
        </div>
        <div class="row g-4">
            @foreach (config('brand.locations') as $location)
                <div class="col-md-4">
                    <article class="location-card">
                        <h3>{{ $location['name'] }}</h3>
                        <p class="small muted mb-2">{{ $location['province'] }}</p>
                        <p class="mb-0">{{ $location['summary'] }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>
