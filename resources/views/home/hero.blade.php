<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="eyebrow">Dumaguete City · Sibulan · Dauin</p>
                <h1>{{ config('brand.tagline') }}</h1>
                <p class="hero-lede">{{ config('brand.name') }} is a local marketplace for home repairs and upkeep in Negros Oriental. Compare professionals, book a visit, and record the payment in your account.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <x-button href="{{ route('workers.index') }}" variant="secondary">See professionals</x-button>
                    <x-button href="{{ route('home') }}#services" variant="outline-light">See services</x-button>
                </div>
                <p class="hero-note">Open a professional, describe the job, and record the payment on the platform. No card is charged. An admin marks the visit in progress, done, or canceled.</p>
            </div>
            <div class="col-lg-5">
                <aside class="booking-preview" aria-labelledby="booking-preview-title">
                    <p class="example-kicker">Example</p>
                    <h2 id="booking-preview-title" class="h5">What a booking includes</h2>
                    <p class="booking-service">Plumbing</p>
                    <p class="booking-place">Dumaguete City</p>
                    <dl>
                        <dt>When</dt>
                        <dd>A morning you choose</dd>
                        <dt>Notes</dt>
                        <dd>A short description of the problem</dd>
                    </dl>
                    <p class="small muted mb-0">A real request starts on a professional’s page. You choose the time, the municipality, and the job notes there.</p>
                </aside>
            </div>
        </div>
    </div>
</section>

<section class="promise-band" aria-label="How the marketplace is set up">
    <div class="container">
        <div class="promise-list">
            @foreach (config('brand.promises') as $promise)
                <article class="promise">
                    <i class="bi {{ $promise['icon'] }}" aria-hidden="true"></i>
                    <div>
                        <h2 class="h6 mb-1">{{ $promise['title'] }}</h2>
                        <p class="mb-0">{{ $promise['summary'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
