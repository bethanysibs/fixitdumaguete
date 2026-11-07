<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $statuses = array_keys(config('marketplace.statuses'));

        $bookings = Booking::query()
            ->with(['customer', 'workerProfile.user', 'service.category', 'location', 'payment'])
            ->when(in_array($status, $statuses, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'status' => $status,
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('marketplace.statuses')))],
        ]);

        $booking->update(['status' => $data['status']]);

        if ($data['status'] === 'canceled' && $booking->payment?->status === 'paid') {
            $booking->payment->update(['status' => 'refunded']);
        }

        return back()->with('success', 'The booking is now '.$booking->statusLabel().'.');
    }
}
