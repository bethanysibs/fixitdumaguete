<?php

namespace App\Http\Controllers;

use App\Models\WorkerProfile;
use Illuminate\Contracts\View\View;

class WorkerProfileController extends Controller
{
    public function show(WorkerProfile $workerProfile): View
    {
        abort_unless($workerProfile->verification_status === 'approved', 404);

        $workerProfile->load([
            'user',
            'locations',
            'services.category',
            'ratings.user',
        ]);
        $workerProfile->loadCount('ratings');
        $workerProfile->loadAvg('ratings', 'score');

        $description = $workerProfile->is_sample
            ? 'Sample profile for the FixItDumaguete preview. This is not a real bookable worker.'
            : $workerProfile->headline;

        return view('workers.show', [
            'workerProfile' => $workerProfile,
            'title' => $workerProfile->user->name.' | '.config('brand.name'),
            'description' => $description,
        ]);
    }
}
