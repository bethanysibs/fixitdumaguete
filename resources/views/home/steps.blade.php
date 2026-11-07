<section class="section-block steps-section" id="how-it-works" aria-labelledby="how-title">
    <div class="container">
        <div class="row section-heading">
            <div class="col-lg-8">
                <h2 id="how-title">How a booking will work</h2>
                <p class="section-intro mb-0">Customers will book only after they have an account. Workers will not be publicly bookable until they are verified.</p>
            </div>
        </div>
        <ol class="row g-4 list-unstyled mb-0">
            @foreach (config('brand.steps') as $step)
                <li class="col-md-4">
                    <article class="step-card">
                        <span class="step-number">Step {{ $loop->iteration }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p class="mb-0">{{ $step['summary'] }}</p>
                    </article>
                </li>
            @endforeach
        </ol>
    </div>
</section>
