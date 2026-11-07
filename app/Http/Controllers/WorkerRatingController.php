<?php

namespace App\Http\Controllers;

use App\Models\WorkerProfile;
use App\Models\WorkerRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkerRatingController extends Controller
{
    public function update(Request $request, WorkerProfile $workerProfile): RedirectResponse
    {
        $this->ensurePublicProfile($workerProfile);
        abort_unless($request->user()?->canRateWorkers(), 403);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
        ]);

        WorkerRating::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'worker_profile_id' => $workerProfile->id,
            ],
            ['score' => $data['score']],
        );

        return back()->with('success', 'Your rating was saved.');
    }

    public function revise(Request $request, WorkerProfile $workerProfile, WorkerRating $rating): RedirectResponse
    {
        $this->ensurePublicProfile($workerProfile);
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($rating->worker_profile_id === $workerProfile->id, 404);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
        ]);

        $rating->update(['score' => $data['score']]);

        return back()->with('success', 'The rating was updated.');
    }

    public function destroy(Request $request, WorkerProfile $workerProfile, WorkerRating $rating): RedirectResponse
    {
        $this->ensurePublicProfile($workerProfile);
        abort_unless($rating->worker_profile_id === $workerProfile->id, 404);

        $user = $request->user();
        abort_unless($user !== null && ($user->isAdmin() || $user->id === $rating->user_id), 403);

        $rating->delete();

        return back()->with('success', 'The rating was removed.');
    }

    private function ensurePublicProfile(WorkerProfile $workerProfile): void
    {
        abort_unless($workerProfile->verification_status === 'approved', 404);
    }
}
