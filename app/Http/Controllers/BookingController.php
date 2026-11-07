<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPhoto;
use App\Models\Payment;
use App\Models\WorkerProfile;
use App\Services\CommissionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'customer', 403);

        $bookings = $request->user()->bookings()
            ->with(['workerProfile.user', 'service.category', 'location', 'payment'])
            ->latest()
            ->paginate(10);

        return view('bookings.index', [
            'bookings' => $bookings,
        ]);
    }

    public function create(Request $request, WorkerProfile $workerProfile): View
    {
        $this->ensureCustomerCanBook($request, $workerProfile);

        $workerProfile->load(['user', 'locations', 'services.category']);

        return view('bookings.create', [
            'workerProfile' => $workerProfile,
            'serviceId' => $request->integer('service'),
        ]);
    }

    public function store(Request $request, WorkerProfile $workerProfile): RedirectResponse
    {
        $this->ensureCustomerCanBook($request, $workerProfile);

        $workerProfile->load(['locations', 'services']);
        $serviceIds = $workerProfile->services->where('is_active', true)->pluck('id');
        $locationIds = $workerProfile->locations->pluck('id');

        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::in($serviceIds)],
            'location_id' => ['required', 'integer', Rule::in($locationIds)],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['required', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:255'],
            'landmark' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'latitude' => ['required', 'regex:/^-?\d{1,3}(\.\d{1,7})?$/'],
            'longitude' => ['required', 'regex:/^-?\d{1,3}(\.\d{1,7})?$/'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'contact_phone.regex' => 'Enter a Philippine mobile number, such as 09171234567.',
            'latitude.required' => 'Mark the home on the map.',
            'longitude.required' => 'Mark the home on the map.',
        ]);

        $location = $workerProfile->locations->firstWhere('id', (int) $data['location_id']);
        $latitude = $this->coordinate($data['latitude']);
        $longitude = $this->coordinate($data['longitude']);

        if ($location->map_latitude !== null && (! $this->near($latitude, (string) $location->map_latitude) || ! $this->near($longitude, (string) $location->map_longitude))) {
            throw ValidationException::withMessages([
                'latitude' => 'Mark the home inside '.$location->name.'.',
            ]);
        }

        $service = $workerProfile->services->firstWhere('id', (int) $data['service_id']);
        $price = (string) $service->price_amount;
        $commission = app(CommissionCalculator::class)->quote(
            $request->user()->id,
            $workerProfile->id,
            $service->service_category_id,
            $price,
        );

        $booking = DB::transaction(function () use ($request, $workerProfile, $service, $data, $latitude, $longitude, $price, $commission) {
            $booking = Booking::query()->create([
                'customer_id' => $request->user()->id,
                'worker_profile_id' => $workerProfile->id,
                'service_id' => $service->id,
                'location_id' => $data['location_id'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $data['address'],
                'landmark' => $data['landmark'],
                'contact_phone' => $data['contact_phone'],
                'scheduled_at' => $data['scheduled_at'],
                'notes' => $data['notes'],
                'status' => 'pending',
                'price_amount' => $price,
                'commission_rate' => $commission['original_rate'],
                'commission_amount' => $commission['amount'],
                'worker_amount' => $commission['worker_amount'],
                'commission_setting_id' => $commission['setting_id'],
                'commission_rule' => $commission['rule'],
                'commission_basis' => $commission['basis'],
                'commission_base_amount' => $commission['base_amount'],
                'commission_original_rate' => $commission['original_rate'],
                'commission_adjustment' => $commission['adjustment'],
            ]);

            foreach ($request->file('photos', []) as $photo) {
                $booking->photos()->create([
                    'path' => $photo->store('booking-photos/'.$booking->id, 'local'),
                ]);
            }

            return $booking;
        });

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The visit is saved. Record the payment to confirm it.');
    }

    public function show(Request $request, Booking $booking): View
    {
        abort_unless($booking->visibleTo($request->user()), 403);

        $booking->load(['customer', 'workerProfile.user', 'service.category', 'location', 'payment', 'photos']);

        if ($request->user()->isAdmin() || $request->user()->id === $booking->workerProfile?->user_id) {
            $booking->load([
                'quotations.items',
                'quotations.creator',
                'quotations.sourceQuotation',
                'quotations.approver',
                'quotations.rejector',
                'quotations.customerAccepter',
                'quotations.customerRejector',
            ]);
        }

        return view('bookings.show', [
            'booking' => $booking,
            'customerCanReviewQuotation' => $request->user()->id === $booking->customer_id
                && $booking->quotations()->whereIn('status', ['admin_approved', 'approved', 'customer_rejected'])->exists(),
            'customerCanPayQuotation' => $request->user()->id === $booking->customer_id
                && $booking->currentQuotation()->where('status', 'approved')->whereNotNull('customer_approved_at')->exists(),
        ]);
    }

    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->customer_id, 403);
        abort_unless($booking->status === 'pending' && $booking->payment === null, 403);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $request->user()->id,
            'amount' => $booking->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);

        $booking->update(['status' => 'confirmed']);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The payment is recorded and the visit is confirmed. No card was charged.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->id === $booking->customer_id, 403);
        abort_unless($booking->status === 'pending' && $booking->payment === null, 403);

        $booking->update(['status' => 'canceled']);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The visit was canceled before payment.');
    }

    public function photo(Request $request, Booking $booking, BookingPhoto $photo): Response
    {
        abort_unless($booking->visibleTo($request->user()), 403);
        abort_unless($photo->booking_id === $booking->id, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }

    private function ensureCustomerCanBook(Request $request, WorkerProfile $workerProfile): void
    {
        abort_unless($request->user()->role === 'customer', 403);
        abort_unless($workerProfile->verification_status === 'approved', 404);
    }

    private function coordinate(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        if (! str_contains($value, '.')) {
            $value .= '.0000000';
        }

        [$whole, $fraction] = explode('.', $value, 2);
        $fraction = substr(str_pad($fraction, 7, '0'), 0, 7);

        return ($negative ? '-' : '').((int) $whole).'.'.$fraction;
    }

    private function near(string $value, string $center): bool
    {
        return abs($this->scale($value) - $this->scale($center)) <= 20000;
    }

    private function scale(string $number): int
    {
        $negative = str_starts_with($number, '-');
        $number = ltrim($number, '-');

        if (! str_contains($number, '.')) {
            $number .= '.00000';
        }

        [$whole, $fraction] = explode('.', $number, 2);
        $fraction = substr(str_pad($fraction, 5, '0'), 0, 5);
        $scaled = ((int) $whole * 100000) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }
}
