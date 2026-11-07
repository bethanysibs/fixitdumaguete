<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerJobController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->workerProfile;

        abort_if($profile === null, 403);

        $bookings = $profile->bookings()
            ->with(['customer', 'service.category', 'location', 'payment'])
            ->latest()
            ->paginate(10);

        return view('jobs.index', [
            'bookings' => $bookings,
        ]);
    }
}
