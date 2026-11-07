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
            Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $version = (int) Quotation::query()->where('booking_id', $booking->id)->max('version_number') + 1;

            Quotation::query()
                ->where('booking_id', $booking->id)
                ->where('is_current', true)
                ->update([
                    'status' => 'superseded',
                    'is_current' => false,
                    'current_booking_id' => null,
                ]);

            $quotation = new Quotation;
            $quotation->booking_id = $booking->id;
            $quotation->version_number = $version;
            $quotation->status = 'draft';
            $quotation->currency = $calculated['currency'];
            $quotation->notes = $notes;
            $quotation->created_by = $createdBy;
            $quotation->subtotal = $calculated['subtotal'];
            $quotation->total_amount = $calculated['total_amount'];
            $quotation->is_current = true;
            $quotation->save();

            foreach ($calculated['items'] as $item) {
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

            return $quotation->load('items');
        });
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
