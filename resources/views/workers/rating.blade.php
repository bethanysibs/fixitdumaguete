@php
    $user = auth()->user();
    $ownRating = $user ? $workerProfile->ratings->firstWhere('user_id', $user->id) : null;
@endphp

<article class="worker-offer rating-panel">
    <h2>Rating</h2>
    @if ($user?->canRateWorkers())
        <form method="POST" action="{{ route('workers.rating.update', $workerProfile) }}">
            @csrf
            @method('PUT')
            <fieldset class="rating-choices">
                <legend class="form-label">Your rating</legend>
                @foreach ([1, 2, 3, 4, 5] as $star)
                    <input class="btn-check" type="radio" name="score" id="score-{{ $star }}" value="{{ $star }}" @checked((int) old('score', $ownRating?->score) === $star) @required($loop->first)>
                    <label class="btn btn-outline-secondary" for="score-{{ $star }}">{{ $star }} <i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden"> {{ $star === 1 ? 'star' : 'stars' }}</span></label>
                @endforeach
            </fieldset>
            @error('score')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <button class="btn btn-secondary mt-3" type="submit">Save rating</button>
        </form>
        @if ($ownRating)
            <form class="mt-2" method="POST" action="{{ route('workers.rating.destroy', [$workerProfile, $ownRating]) }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-link px-0" type="submit">Remove your rating</button>
            </form>
        @endif
    @else
        <p class="mb-0"><a href="{{ route('login') }}">Log in</a> as a customer or an admin to rate this professional.</p>
    @endif

    @if ($user?->isAdmin())
        <div class="admin-ratings">
            <h3 class="h6">Ratings on this profile</h3>
            @forelse ($workerProfile->ratings as $rating)
                <form class="admin-rating" method="POST" action="{{ route('workers.rating.revise', [$workerProfile, $rating]) }}">
                    @csrf
                    @method('PUT')
                    <p class="mb-2"><strong>{{ $rating->user->name }}</strong> <span class="worker-meta">{{ $rating->user->role === 'admin' ? 'Admin' : 'Customer' }}</span></p>
                    <label class="visually-hidden" for="admin-score-{{ $rating->id }}">Score for {{ $rating->user->name }}</label>
                    <select class="form-select" id="admin-score-{{ $rating->id }}" name="score">
                        @for ($star = 1; $star <= 5; $star++)
                            <option value="{{ $star }}" @selected($rating->score === $star)>{{ $star }}</option>
                        @endfor
                    </select>
                    <button class="btn btn-secondary" type="submit">Update</button>
                </form>
                @if ($rating->user_id !== $user->id)
                    <form method="POST" action="{{ route('workers.rating.destroy', [$workerProfile, $rating]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-link px-0" type="submit">Remove</button>
                    </form>
                @endif
            @empty
                <p class="mb-0">No ratings yet.</p>
            @endforelse
        </div>
    @endif
</article>
