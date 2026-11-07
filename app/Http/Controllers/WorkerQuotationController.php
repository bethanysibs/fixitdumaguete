<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Quotation;
use App\Services\QuotationVersioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerQuotationController extends Controller
{
    public function __construct(private QuotationVersioner $versions) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAssignedWorker($request, $booking);
        $this->ensureNewQuotation($booking);
        abort_if($booking->quotations()->exists(), 403);

        $this->versions->append($booking, $this->items($request), $request->user()->id, $this->notes($request));

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The quotation draft is saved.');
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAssignedWorker($request, $booking);
        $this->ensureQuotationEdit($booking);

        $this->versions->replaceDraft($this->currentDraft($booking), $this->items($request), $this->notes($request));

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The quotation draft is saved.');
    }

    public function confirm(Request $request, Booking $booking): View
    {
        $this->ensureAssignedWorker($request, $booking);
        $this->ensureNewQuotation($booking);

        $quotation = $this->currentDraft($booking);
        $quotation->load(['items', 'creator']);

        return view('quotations.confirm', [
            'booking' => $booking->load(['service.category', 'workerProfile.user']),
            'quotation' => $quotation,
        ]);
    }

    public function submit(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAssignedWorker($request, $booking);
        $this->ensureNewQuotation($booking);

        $this->versions->submit($this->currentDraft($booking));

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'The quotation was submitted for admin review.');
    }

    public function revise(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAssignedWorker($request, $booking);
        $this->ensureNewQuotation($booking);

        $current = $booking->currentQuotation()->first();
        abort_unless($current !== null && $current->status === 'submitted', 403);

        $this->versions->startRevision($current, $request->user()->id);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'A new draft was created. The submitted quotation was kept.');
    }

    private function ensureAssignedWorker(Request $request, Booking $booking): void
    {
        $booking->loadMissing('workerProfile');

        abort_unless($request->user()->id === $booking->workerProfile?->user_id, 403);
    }

    private function ensureNewQuotation(Booking $booking): void
    {
        abort_unless($booking->allowsNewQuotation(), 403);
    }

    private function ensureQuotationEdit(Booking $booking): void
    {
        abort_unless($booking->allowsQuotationEdits(), 403);
    }

    private function currentDraft(Booking $booking): Quotation
    {
        $current = $booking->currentQuotation()->first();

        abort_unless($current !== null && $current->status === 'draft', 403);

        return $current;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Request $request): array
    {
        $items = $request->input('items', []);

        if (! is_array($items)) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one quotation item.',
            ]);
        }

        return array_values($items);
    }

    private function notes(Request $request): ?string
    {
        $notes = $request->input('notes');

        if ($notes !== null && ! is_string($notes)) {
            throw ValidationException::withMessages([
                'notes' => 'Enter notes of 2,000 characters or fewer.',
            ]);
        }

        return $notes;
    }
}
