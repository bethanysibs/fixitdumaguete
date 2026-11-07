<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Quotation;
use App\Services\ContractAmountCalculator;
use App\Services\QuotationVersioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationVersioner $versions,
        private ContractAmountCalculator $contracts,
    ) {}

    public function show(Request $request, Booking $booking): View
    {
        $this->ensureAdmin($request);

        $booking = $this->booking($booking);

        return view('admin.quotations.show', [
            'booking' => $booking,
            'contract' => $this->contracts->summarize($booking),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAdmin($request);
        $quotation = $this->adjustableQuotation($request, $booking);
        $items = $this->items($request);
        $notes = $this->notes($request);
        $adminNotes = $this->adminNotes($request);

        // An internal note is not part of the quoted price. Saving only that note
        // keeps this version. A line-item or customer-note change starts a new one.
        if ($this->versions->matches($quotation, $items, $notes)) {
            $saved = $this->versions->rememberAdminNote($quotation, $adminNotes);

            return redirect()
                ->route('admin.quotations.show', $booking)
                ->with('success', $saved
                    ? 'The internal note was saved. The quotation version was kept.'
                    : 'No quotation changes were saved.');
        }

        $this->versions->adjust($quotation, $items, $request->user()->id, $notes, $adminNotes);

        return redirect()
            ->route('admin.quotations.show', $booking)
            ->with('success', 'The adjusted quotation was saved as a new version. The earlier version was kept.');
    }

    public function confirm(Request $request, Booking $booking): View
    {
        $this->ensureAdmin($request);
        $quotation = $this->submittedQuotation($request, $booking);
        $quotation->load(['items', 'creator', 'sourceQuotation']);

        return view('admin.quotations.confirm', [
            'booking' => $booking->load(['customer', 'workerProfile.user', 'service.category']),
            'quotation' => $quotation,
        ]);
    }

    public function approve(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAdmin($request);

        $this->versions->approve($this->submittedQuotation($request, $booking), $request->user()->id);

        return redirect()
            ->route('admin.quotations.show', $booking)
            ->with('success', 'The quotation is approved for customer review.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensureAdmin($request);
        $quotation = $this->submittedQuotation($request, $booking);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->versions->reject(
            $quotation,
            $request->user()->id,
            $data['rejection_reason'],
            $data['admin_notes'] ?? null,
        );

        return redirect()
            ->route('admin.quotations.show', $booking)
            ->with('success', 'The quotation was rejected. The worker can prepare a new draft.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function booking(Booking $booking): Booking
    {
        return $booking->load([
            'customer',
            'workerProfile.user',
            'service.category',
            'location',
            'quotationRequests.quotations.items',
            'quotationRequests.quotations.creator',
            'quotationRequests.quotations.sourceQuotation',
            'quotationRequests.quotations.approver',
            'quotationRequests.quotations.rejector',
            'quotationRequests.quotations.customerAccepter',
            'quotationRequests.quotations.customerRejector',
        ]);
    }

    private function submittedQuotation(Request $request, Booking $booking): Quotation
    {
        abort_unless($booking->allowsNewQuotation(), 403);

        $quotation = $booking->currentQuotationFor(
            $this->requestId($request),
            fn (Quotation $quotation) => $quotation->status === 'submitted',
        );
        abort_unless($quotation !== null, 403);

        return $quotation;
    }

    private function adjustableQuotation(Request $request, Booking $booking): Quotation
    {
        abort_unless($booking->allowsNewQuotation(), 403);

        $quotation = $booking->currentQuotationFor(
            $this->requestId($request),
            fn (Quotation $quotation) => in_array($quotation->status, ['submitted', 'admin_approved', 'approved'], true),
        );
        abort_unless($quotation !== null, 403);

        return $quotation->load('items');
    }

    private function requestId(Request $request): ?int
    {
        $value = $request->input('quotation_request_id');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
            throw ValidationException::withMessages([
                'quotation_request_id' => 'Choose the additional quotation to review.',
            ]);
        }

        return (int) $value;
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

    private function adminNotes(Request $request): ?string
    {
        $notes = $request->input('admin_notes');

        if ($notes !== null && ! is_string($notes)) {
            throw ValidationException::withMessages([
                'admin_notes' => 'Enter 2,000 characters or fewer.',
            ]);
        }

        return $notes;
    }
}
