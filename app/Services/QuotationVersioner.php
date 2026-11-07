<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Quotation;
use App\Models\QuotationItem;
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
        if ($quotation->status !== 'submitted' || ! $quotation->is_current) {
            throw ValidationException::withMessages([
                'quotation' => 'Revise the submitted quotation to prepare another draft.',
            ]);
        }

        $quotation->load('items');
        $calculated = $this->calculator->calculate($this->itemPayload($quotation));
        $notes = $this->notes($quotation->notes);

        return DB::transaction(function () use ($quotation, $calculated, $createdBy, $notes) {
            $this->lockBooking($quotation->booking_id);
            $quotation->refresh();

            if ($quotation->status !== 'submitted' || ! $quotation->is_current) {
                throw ValidationException::withMessages([
                    'quotation' => 'Revise the submitted quotation to prepare another draft.',
                ]);
            }

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
            );
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
    private function persist(int $bookingId, int $version, array $calculated, ?int $createdBy, ?string $notes): Quotation
    {
        $quotation = new Quotation;
        $quotation->booking_id = $bookingId;
        $quotation->version_number = $version;
        $quotation->status = 'draft';
        $quotation->currency = $calculated['currency'];
        $quotation->notes = $notes;
        $quotation->created_by = $createdBy;
        $quotation->subtotal = $calculated['subtotal'];
        $quotation->total_amount = $calculated['total_amount'];
        $quotation->is_current = true;
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
}
