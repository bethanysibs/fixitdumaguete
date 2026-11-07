@extends('layouts.app')

@section('title', 'Book '.$workerProfile->user->name.' | '.config('brand.name'))
@section('meta_description', 'Request a visit with '.$workerProfile->user->name.' and record the payment on '.config('brand.name').'.')

@section('content')
    <section class="section-block">
        <div class="container">
            <x-breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Professionals', 'url' => route('workers.index')],
                ['label' => $workerProfile->user->name, 'url' => route('workers.show', $workerProfile)],
                ['label' => 'Book'],
            ]" />

            <div class="row g-4 mt-1">
                <div class="col-lg-8">
                    <h1>Book {{ $workerProfile->user->name }}</h1>
                    <p class="lede">Mark the home, describe the problem, and choose a time. The price is saved with the booking. Payment is recorded on {{ config('brand.name') }} and no card is charged.</p>

                    @if ($workerProfile->is_sample)
                        <p class="sample-banner">This is a sample professional. The booking is a preview on the site.</p>
                    @endif

                    <form class="booking-form mt-4" method="POST" action="{{ route('bookings.store', $workerProfile) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="service_id">Service <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                                @foreach ($workerProfile->services->where('is_active', true) as $service)
                                    <option value="{{ $service->id }}" @selected((int) old('service_id', $serviceId) === $service->id)>
                                        {{ $service->category->name }} — {{ $service->formattedPrice() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="location_id">Municipality <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('location_id') is-invalid @enderror" id="location_id" name="location_id" required>
                                @foreach ($workerProfile->locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) old('location_id', $workerProfile->locations->first()->id) === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <h2 class="h5">Where is the home?</h2>
                        <div class="mb-3">
                            <label class="form-label" for="address">Address <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address') }}" maxlength="255" autocomplete="off" placeholder="Street, barangay, or building" required>
                            <div class="form-text">Type an address and the pin moves to it. Pin the map and this address fills in.</div>
                            <div id="address-lookup" class="form-text" role="status"></div>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-visit-map :locations="$workerProfile->locations" :selected="old('location_id', $workerProfile->locations->first()->id)" :latitude="old('latitude')" :longitude="old('longitude')" />

                        <div class="mb-3">
                            <label class="form-label" for="landmark">How to find the home <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('landmark') is-invalid @enderror" id="landmark" name="landmark" value="{{ old('landmark') }}" maxlength="255" required>
                            <div class="form-text">A landmark, gate, or building the worker can look for.</div>
                            @error('landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-form-input name="contact_phone" label="Mobile number" :value="old('contact_phone')" inputmode="tel" placeholder="09171234567" hint="Shown only on this booking, to the worker and an admin." required />
                        <x-form-input name="scheduled_at" type="datetime-local" label="When" :value="old('scheduled_at')" min="{{ now()->format('Y-m-d\TH:i') }}" required />

                        <h2 class="h5">What is the problem?</h2>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Describe the problem <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="5" maxlength="2000" required>{{ old('notes') }}</textarea>
                            <div class="form-text">What is wrong, where it is in the home, and anything the worker should bring or watch for.</div>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="photos">Photos of the problem</label>
                            <input class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                            <div class="form-text">Optional. Up to 3 photos, JPG, PNG, or WebP.</div>
                            @error('photos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-button type="submit">Save visit</x-button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
