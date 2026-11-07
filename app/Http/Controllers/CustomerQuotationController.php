<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Quotation;
use App\Services\QuotationVersioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerQuotationController extends Controller
{
    public function __construct(private QuotationVersioner $versions) {}

    public function show(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);

        $booking->load([
            'service.category',
            'workerProfile.user',
            'quotations.items',
        ]);

        return view('quotations.customer', [
            'booking' => $booking,
        ]);
    }

    public function confirm(Request $request, Booking $booking): View
    {
        $this->ensureOwner($request, $booking);
        $quotation = $this->decidable($booking);
        $quotation->load('items');

        return view('quotations.customer-confirm', [
            'booking' => $booking->load(['service.category', 'workerProfile.user']),
            'quotation' => $quotation,
        ]);
    }

    public function accept(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureOwner($request, $booking);
        $quotation = $this->decidable($booking);
        $data = $request->validate([
            'version_number' => ['required', 'integer'],
        ]);

        $this->versions->acceptByCustomer(
            $quotation,
            $request->user()->id,
            $this->versionNumber($request, $quotation, (int) $data['version_number']),
        );

        return redirect()
            ->route('bookings.quotation.show', $booking)
            ->with('success', 'You accepted this quotation. No payment was recorded, and the visit price is unchanged.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureOwner($request, $booking);
        $quotation = $this->decidable($booking);

        $data = $request->validate([
            'version_number' => ['required', 'integer'],
            'customer_rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->versions->rejectByCustomer(
            $quotation,
            $request->user()->id,
            $this->versionNumber($request, $quotation, (int) $data['version_number']),
            $data['customer_rejection_reason'],
        );

        return redirect()
            ->route('bookings.quotation.show', $booking)
            ->with('success', 'You declined this quotation. The worker can prepare a revision.');
    }

    private function ensureOwner(Request $request, Booking $booking): void
    {
        abort_unless($request->user()?->id === $booking->customer_id, 403);
    }

    private function decidable(Booking $booking): Quotation
    {
        abort_unless($booking->allowsNewQuotation(), 403);

        $quotation = $booking->currentQuotation()->first();

        abort_unless($quotation !== null
            && $quotation->is_current
            && $quotation->status === 'admin_approved'
            && $quotation->admin_approved_at !== null
            && $quotation->customer_approved_at === null
            && $quotation->customer_rejected_at === null, 403);

        return $quotation;
    }

    private function versionNumber(Request $request, Quotation $quotation, ?int $version = null): int
    {
        $version ??= $request->integer('version_number');

        if ($request->input('version_number') === null || $version !== $quotation->version_number) {
            throw ValidationException::withMessages([
                'version_number' => 'This quotation changed. Review it again before deciding.',
            ]);
        }

        return $version;
    }
}
