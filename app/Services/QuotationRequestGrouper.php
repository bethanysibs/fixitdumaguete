<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QuotationRequestGrouper
{
    /**
     * Existing quotation versions for a booking were one chain.
     * Keep that chain as additional quotation 1 and preserve version numbers.
     */
    public static function groupUnassigned(): void
    {
        $bookingIds = DB::table('quotations')
            ->whereNull('quotation_request_id')
            ->distinct()
            ->pluck('booking_id');

        foreach ($bookingIds as $bookingId) {
            $first = DB::table('quotations')
                ->where('booking_id', $bookingId)
                ->whereNull('quotation_request_id')
                ->orderBy('id')
                ->first();

            if ($first === null) {
                continue;
            }

            $number = (int) DB::table('quotation_requests')->where('booking_id', $bookingId)->max('request_number') + 1;
            $requestId = DB::table('quotation_requests')->insertGetId([
                'booking_id' => $bookingId,
                'request_number' => $number,
                'title' => 'Additional quotation '.$number,
                'description' => null,
                'status' => 'open',
                'created_by' => $first->created_by,
                'created_at' => $first->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('quotations')
                ->where('booking_id', $bookingId)
                ->whereNull('quotation_request_id')
                ->update(['quotation_request_id' => $requestId]);

            DB::table('quotations')
                ->where('booking_id', $bookingId)
                ->where('quotation_request_id', $requestId)
                ->where('is_current', true)
                ->update(['current_request_id' => $requestId]);
        }
    }
}
