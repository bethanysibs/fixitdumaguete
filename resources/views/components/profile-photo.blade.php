@props(['profile'])

@if ($profile->photoUrl())
    <img src="{{ $profile->photoUrl() }}" alt="Photo of {{ $profile->user->name }}" {{ $attributes->merge(['class' => 'profile-photo']) }}>
@else
    <span {{ $attributes->merge(['class' => 'profile-photo profile-photo-empty']) }} aria-hidden="true">{{ $profile->initials() }}</span>
@endif
