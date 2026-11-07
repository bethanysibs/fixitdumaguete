@extends('layouts.app')

@section('title', 'Profile | '.config('brand.name'))

@section('content')
    @php
        $action = fn (string $name, array $params = []) => $managing
            ? route('admin.workers.'.$name, array_merge([$profile], $params))
            : route('work.'.$name, $params);
    @endphp
    <section class="section-block">
        <div class="container">
            <h1>{{ $profile->user->name }}</h1>
            <p class="lede">Update the public profile, photo, skills, finished projects, and portfolio. Customers see an approved profile on the professionals page.</p>
            @if ($profile->verification_status !== 'approved')
                <p class="sample-banner">This profile is {{ $profile->verification_status }}. Customers cannot see it until an admin approves it.</p>
            @endif

            <div class="row g-4 mt-1">
                <div class="col-lg-4">
                    <div class="admin-panel">
                        <h2 class="h5">Profile photo</h2>
                        <x-profile-photo :profile="$profile" class="profile-photo-profile mb-3" />
                        <form method="POST" action="{{ $action('photo.update') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="photo">Upload a photo</label>
                                <input class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                                <div class="form-text">JPG, PNG, or WebP. Up to 2 MB. This is the picture on the professionals list.</div>
                                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-primary" type="submit">Save photo</button>
                        </form>
                        @if ($profile->photo_path)
                            <form class="mt-3" method="POST" action="{{ $action('photo.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-secondary" type="submit">Remove photo</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="col-lg-8">
                    <form class="admin-panel" method="POST" action="{{ $action('update') }}">
                        @csrf
                        @method('PUT')
                        <h2 class="h5">About</h2>
                        <x-form-input name="headline" label="Headline" :value="old('headline', $profile->headline)" required />
                        <div class="mb-3">
                            <label class="form-label" for="bio">About the work <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea class="form-control @error('bio') is-invalid @enderror" id="bio" name="bio" rows="5" required>{{ old('bio', $profile->bio) }}</textarea>
                            @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-form-input name="years_experience" type="number" label="Years of experience" :value="old('years_experience', $profile->years_experience)" min="0" max="60" />
                        <x-form-input name="availability_note" label="Availability" :value="old('availability_note', $profile->availability_note)" maxlength="160" hint="For example, weekday mornings." />
                        <fieldset class="mb-3">
                            <legend class="form-label">Service areas</legend>
                            @foreach ($locations as $location)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="locations[]" id="location-{{ $location->id }}" value="{{ $location->id }}" @checked(collect(old('locations', $profile->locations->pluck('id')->all()))->map(fn ($id) => (int) $id)->contains($location->id))>
                                    <label class="form-check-label" for="location-{{ $location->id }}">{{ $location->name }}</label>
                                </div>
                            @endforeach
                            @error('locations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                        @if ($managing)
                            <div class="mb-3">
                                <label class="form-label" for="verification_status">Verification</label>
                                <select class="form-select" id="verification_status" name="verification_status">
                                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('verification_status', $profile->verification_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <button class="btn btn-primary" type="submit">Save profile</button>
                    </form>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <div class="admin-panel">
                        <h2 class="h5">Skills</h2>
                        <div class="skill-list mb-3">
                            @forelse ($profile->skills as $skill)
                                <form method="POST" action="{{ $action('skills.destroy', [$skill]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <span class="skill-pill">{{ $skill->name }} <button class="btn btn-link btn-sm p-0" type="submit">Remove</button></span>
                                </form>
                            @empty
                                <p class="mb-0">No skills yet.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ $action('skills.store') }}">
                            @csrf
                            <x-form-input name="name" label="Add a skill" maxlength="40" required />
                            <button class="btn btn-secondary" type="submit">Add skill</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="admin-panel">
                        <h2 class="h5">Services and prices</h2>
                        @foreach ($profile->services as $service)
                            <p class="mb-1"><strong>{{ $service->category->name }}</strong> · {{ $service->formattedPrice() }}</p>
                            <form class="mb-3" method="POST" action="{{ $action('services.destroy', [$service]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-secondary btn-sm" type="submit">Remove</button>
                            </form>
                        @endforeach
                        <form method="POST" action="{{ $action('services.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="service_category_id">Service</label>
                                <select class="form-select @error('service_category_id') is-invalid @enderror" id="service_category_id" name="service_category_id" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((int) old('service_category_id') === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('service_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="summary">What this visit covers</label>
                                <textarea class="form-control" id="summary" name="summary" rows="3" required>{{ old('summary') }}</textarea>
                            </div>
                            <x-form-input name="price_amount" label="Price" :value="old('price_amount')" inputmode="decimal" placeholder="850.00" required />
                            <button class="btn btn-secondary" type="submit">Add service</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <div class="admin-panel">
                        <h2 class="h5">Projects done</h2>
                        @forelse ($profile->projects as $project)
                            <article class="mb-3">
                                <h3 class="h6 mb-1">{{ $project->title }}@if ($project->completed_year) · {{ $project->completed_year }}@endif</h3>
                                <p class="mb-2">{{ $project->summary }}</p>
                                <form method="POST" action="{{ $action('projects.destroy', [$project]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Remove</button>
                                </form>
                            </article>
                        @empty
                            <p>No projects yet.</p>
                        @endforelse
                        <form method="POST" action="{{ $action('projects.store') }}">
                            @csrf
                            <x-form-input name="title" label="Project title" maxlength="120" required />
                            <div class="mb-3">
                                <label class="form-label" for="project-summary">What was done</label>
                                <textarea class="form-control" id="project-summary" name="summary" rows="3" required></textarea>
                            </div>
                            <x-form-input name="completed_year" type="number" label="Year finished" min="1980" :max="now()->year" />
                            <button class="btn btn-secondary" type="submit">Add project</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="admin-panel">
                        <h2 class="h5">Portfolio photos</h2>
                        <div class="portfolio-grid mb-3">
                            @foreach ($profile->portfolioImages as $image)
                                <figure class="mb-0">
                                    <img src="{{ $image->url() }}" alt="{{ $image->caption ?: 'Portfolio photo' }}">
                                    <form method="POST" action="{{ $action('portfolio.destroy', [$image]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-secondary btn-sm mt-2" type="submit">Remove</button>
                                    </form>
                                </figure>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ $action('portfolio.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="image">Sample of finished work</label>
                                <input class="form-control @error('image') is-invalid @enderror" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <x-form-input name="caption" label="Caption" maxlength="160" />
                            <button class="btn btn-secondary" type="submit">Add portfolio photo</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
