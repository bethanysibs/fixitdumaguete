<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationVersioner
{
    public function __construct(private QuotationCalculator $calculator) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function append(Booking $booking, array $items, ?int $createdBy = null, ?string $notes = null): Quotation
    {
        $calculated = $this->calculator->calculate($items);
        $notes = $this->notes($notes);

        return DB::transaction(function () use ($booking, $calculated, $createdBy, $notes) {
            $this->lockBooking($booking->id);

            $version = $this->nextVersion($booking->id);

            Quotation::query()
                ->where('booking_id', $booking->id)
                ->where('is_current', true)
                ->update([
                    'status' => 'superseded',
                    'is_current' => false,
                    'current_booking_id' => null,
                ]);

            return $this->persist($booking->id, $version, $calculated, $createdBy, $notes);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function replaceDraft(Quotation $quotation, array $items, ?string $notes = null): Quotation
    {
        $this->ensureCurrentDraft($quotation);
        $calculated = $this->calculator->calculate($items);
        $notes = $this->notes($notes);

        return DB::transaction(function () use ($quotation, $calculated, $notes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureCurrentDraft($quotation);

            $quotation->currency = $calculated['currency'];
            $quotation->notes = $notes;
            $quotation->subtotal = $calculated['subtotal'];
            $quotation->total_amount = $calculated['total_amount'];
            $quotation->save();

            $quotation->items()->delete();
            $this->storeItems($quotation, $calculated['items']);

            return $quotation->load('items');
        });
    }

    public function submit(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureCurrentDraft($quotation);
            $quotation->load('items');

            $calculated = $this->calculator->calculate($this->itemPayload($quotation));

            foreach ($quotation->items->values() as $index => $item) {
                $item->line_total = $calculated['items'][$index]['line_total'];
                $item->save();
            }

            $quotation->currency = $calculated['currency'];
            $quotation->subtotal = $calculated['subtotal'];
            $quotation->total_amount = $calculated['total_amount'];
            $quotation->status = 'submitted';
            $quotation->submitted_at = now();
            $quotation->save();

            return $quotation->load('items');
        });
    }

    public function startRevision(Quotation $quotation, ?int $createdBy = null): Quotation
    {
        $this->ensureRevisable($quotation);

        $quotation->load('items');
        $calculated = $this->calculator->calculate($this->itemPayload($quotation));
        $notes = $this->notes($quotation->notes);

        return DB::transaction(function () use ($quotation, $calculated, $createdBy, $notes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureRevisable($quotation);
            $this->ensureBookingCanBeReviewed($quotation->booking_id);

            Quotation::query()->whereKey($quotation->id)->update([
                'is_current' => false,
                'current_booking_id' => null,
            ]);

            return $this->persist(
                $quotation->booking_id,
                $this->nextVersion($quotation->booking_id),
                $calculated,
                $createdBy,
                $notes,
                $quotation->id,
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function matches(Quotation $quotation, array $items, ?string $notes): bool
    {
        $calculated = $this->calculator->calculate($items);
        $notes = $this->notes($notes);
        $quotation->loadMissing('items');

        if ($notes !== $quotation->notes || count($calculated['items']) !== $quotation->items->count()) {
            return false;
        }

        foreach ($calculated['items'] as $index => $item) {
            $stored = $quotation->items->values()->get($index);

            if ($stored === null
                || $item['item_type'] !== $stored->item_type
                || $item['description'] !== $stored->description
                || $item['unit'] !== $stored->unit
                || Money::toCents($item['quantity']) !== Money::toCents((string) $stored->quantity)
                || Money::toCents($item['unit_price']) !== Money::toCents((string) $stored->unit_price)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function adjust(Quotation $quotation, array $items, int $adminId, ?string $notes, ?string $adminNotes): Quotation
    {
        $this->ensureAdjustable($quotation);
        $calculated = $this->calculator->calculate($items);
        $notes = $this->notes($notes);
        $adminNotes = $this->boundedText($adminNotes, 'admin_notes');

        return DB::transaction(function () use ($quotation, $calculated, $adminId, $notes, $adminNotes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureAdjustable($quotation);
            $this->ensureBookingCanBeReviewed($quotation->booking_id);

            Quotation::query()->whereKey($quotation->id)->update([
                'is_current' => false,
                'current_booking_id' => null,
            ]);

            return $this->persist(
                $quotation->booking_id,
                $this->nextVersion($quotation->booking_id),
                $calculated,
                $adminId,
                $notes,
                $quotation->id,
                true,
                'submitted',
                $adminNotes,
            );
        });
    }

    public function rememberAdminNote(Quotation $quotation, ?string $adminNotes): bool
    {
        $adminNotes = $this->boundedText($adminNotes, 'admin_notes');

        return DB::transaction(function () use ($quotation, $adminNotes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureAdjustable($quotation);
            $this->ensureBookingCanBeReviewed($quotation->booking_id);

            if ($quotation->admin_notes === $adminNotes) {
                return false;
            }

            $quotation->admin_notes = $adminNotes;
            $quotation->save();

            return true;
        });
    }

    public function approve(Quotation $quotation, int $adminId): Quotation
    {
        return DB::transaction(function () use ($quotation, $adminId) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureBookingCanBeReviewed($quotation->booking_id);

            if (! $quotation->is_current || $quotation->status !== 'submitted' || $quotation->admin_approved_at !== null) {
                throw ValidationException::withMessages([
                    'quotation' => 'Approve the current submitted quotation.',
                ]);
            }

            $quotation->status = 'admin_approved';
            $quotation->admin_approved_by = $adminId;
            $quotation->admin_approved_at = now();
            $quotation->save();

            return $quotation;
        });
    }

    public function reject(Quotation $quotation, int $adminId, string $reason, ?string $adminNotes = null): Quotation
    {
        $reason = $this->boundedText($reason, 'rejection_reason');
        $adminNotes = $this->boundedText($adminNotes, 'admin_notes');

        if ($reason === null) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Enter a reason for rejecting this quotation.',
            ]);
        }

        return DB::transaction(function () use ($quotation, $adminId, $reason, $adminNotes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();
            $this->ensureBookingCanBeReviewed($quotation->booking_id);

            if (! $quotation->is_current || $quotation->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'quotation' => 'Reject the current submitted quotation.',
                ]);
            }

            $quotation->status = 'rejected';
            $quotation->rejected_by = $adminId;
            $quotation->rejected_at = now();
            $quotation->rejection_reason = $reason;
            $quotation->admin_notes = $adminNotes;
            $quotation->save();

            return $quotation->load('items');
        });
    }

    private function ensureCurrentDraft(Quotation $quotation): void
    {
        if ($quotation->status !== 'draft' || ! $quotation->is_current) {
            throw ValidationException::withMessages([
                'quotation' => 'Only the current draft can be edited.',
            ]);
        }
    }

    private function ensureRevisable(Quotation $quotation): void
    {
        if (! $quotation->canBeRevisedByWorker()) {
            throw ValidationException::withMessages([
                'quotation' => 'Revise the submitted quotation to prepare another draft.',
            ]);
        }
    }

    private function ensureAdjustable(Quotation $quotation): void
    {
        if (! $quotation->is_current || ! in_array($quotation->status, ['submitted', 'admin_approved'], true)) {
            throw ValidationException::withMessages([
                'quotation' => 'Adjust the current submitted quotation.',
            ]);
        }
    }

    private function ensureBookingCanBeReviewed(int $bookingId): void
    {
        $booking = Booking::query()->findOrFail($bookingId);

        if (! $booking->allowsNewQuotation()) {
            throw ValidationException::withMessages([
                'quotation' => 'This visit cannot receive a quotation review.',
            ]);
        }
    }

    private function lockBooking(int $bookingId): void
    {
        Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
    }

    private function nextVersion(int $bookingId): int
    {
        return (int) Quotation::query()->where('booking_id', $bookingId)->max('version_number') + 1;
    }

    /**
     * @param  array{currency: string, subtotal: string, total_amount: string, items: list<array<string, mixed>>}  $calculated
     */
    private function persist(
        int $bookingId,
        int $version,
        array $calculated,
        ?int $createdBy,
        ?string $notes,
        ?int $sourceQuotationId = null,
        bool $adminAdjustment = false,
        string $status = 'draft',
        ?string $adminNotes = null,
    ): Quotation {
        $quotation = new Quotation;
        $quotation->booking_id = $bookingId;
        $quotation->source_quotation_id = $sourceQuotationId;
        $quotation->version_number = $version;
        $quotation->status = $status;
        $quotation->currency = $calculated['currency'];
        $quotation->notes = $notes;
        $quotation->admin_notes = $adminNotes;
        $quotation->created_by = $createdBy;
        $quotation->subtotal = $calculated['subtotal'];
        $quotation->total_amount = $calculated['total_amount'];
        $quotation->is_current = true;
        $quotation->is_admin_adjustment = $adminAdjustment;
        $quotation->submitted_at = $status === 'submitted' ? now() : null;
        $quotation->save();

        $this->storeItems($quotation, $calculated['items']);

        return $quotation->load('items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function storeItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $line = new QuotationItem;
            $line->quotation_id = $quotation->id;
            $line->item_type = $item['item_type'];
            $line->description = $item['description'];
            $line->quantity = $item['quantity'];
            $line->unit = $item['unit'];
            $line->unit_price = $item['unit_price'];
            $line->line_total = $item['line_total'];
            $line->sort_order = $item['sort_order'];
            $line->save();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemPayload(Quotation $quotation): array
    {
        return $quotation->items->map(fn (QuotationItem $item) => [
            'item_type' => $item->item_type,
            'description' => $item->description,
            'quantity' => (string) $item->quantity,
            'unit' => $item->unit,
            'unit_price' => (string) $item->unit_price,
        ])->all();
    }

    private function notes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages([
                'notes' => 'Enter notes of 2,000 characters or fewer.',
            ]);
        }

        return $notes;
    }

    private function boundedText(?string $value, string $field): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 2000) {
            throw ValidationException::withMessages([
                $field => 'Enter 2,000 characters or fewer.',
            ]);
        }

        return $value;
    }
}
