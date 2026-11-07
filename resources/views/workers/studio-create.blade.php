@extends('layouts.app')

@section('title', 'Create profile | '.config('brand.name'))

@section('content')
    <section class="section-block">
        <div class="container col-lg-8">
            <h1>Public profile for {{ $worker->name }}</h1>
            <p class="lede">Create the listing first. The photo, skills, projects, and portfolio can be added on the next page. Customers see the profile after an admin approves it.</p>
            <form class="admin-panel mt-4" method="POST" action="{{ $managing ? route('admin.workers.store', $worker) : route('work.store') }}">
                @csrf
                <x-form-input name="headline" label="Headline" :value="old('headline')" required />
                <div class="mb-3">
                    <label class="form-label" for="bio">About the work <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea class="form-control" id="bio" name="bio" rows="5" required>{{ old('bio') }}</textarea>
                </div>
                <x-form-input name="years_experience" type="number" label="Years of experience" :value="old('years_experience')" min="0" max="60" />
                <x-form-input name="availability_note" label="Availability" :value="old('availability_note')" maxlength="160" />
                <fieldset class="mb-3">
                    <legend class="form-label">Service areas</legend>
                    @foreach ($locations as $location)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="locations[]" id="location-{{ $location->id }}" value="{{ $location->id }}" @checked(in_array($location->id, array_map('intval', old('locations', [])), true))>
                            <label class="form-check-label" for="location-{{ $location->id }}">{{ $location->name }}</label>
                        </div>
                    @endforeach
                </fieldset>
                @if ($managing)
                    <div class="mb-3">
                        <label class="form-label" for="verification_status">Verification</label>
                        <select class="form-select" id="verification_status" name="verification_status">
                            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('verification_status', 'pending') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="btn btn-primary" type="submit">Create profile</button>
            </form>
        </div>
    </section>
@endsection
