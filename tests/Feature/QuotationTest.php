<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\QuotationCalculator;
use App\Services\QuotationVersioner;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_a_quotation_and_its_items_belong_to_one_booking(): void
    {
        $booking = $this->booking();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        $quotation = app(QuotationVersioner::class)->append($booking, [
            $this->item('labor', 'Replace the kitchen trap', '1.50', 'hour', '80.00'),
            $this->item('material', 'Trap assembly', '1', 'piece', '45.50'),
        ], $admin->id, 'Bring the old trap down.');

        $this->assertTrue($quotation->booking->is($booking));
        $this->assertTrue($booking->quotations()->first()->is($quotation));
        $this->assertTrue($booking->currentQuotation->is($quotation));
        $this->assertCount(2, $quotation->items);
        $this->assertTrue($quotation->items->every(fn (QuotationItem $item) => $item->quotation->is($quotation)));
        $this->assertTrue($quotation->creator->is($admin));
        $this->assertSame('PHP', $quotation->currency);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('Bring the old trap down.', $quotation->notes);
    }

    public function test_line_totals_subtotal_and_total_ignore_client_amounts(): void
    {
        $booking = $this->booking();
        $financial = $this->financial($booking);

        $result = app(QuotationCalculator::class)->calculate([
            $this->item('labor', 'Replace the kitchen trap', '1.50', 'hour', '80.00', [
                'line_total' => '1.00',
                'total_amount' => '1.00',
                'sort_order' => 99,
            ]),
            $this->item('service_charge', 'Call-out', '1.25', 'visit', '10.10', [
                'line_total' => '9999.99',
            ]),
            $this->item('inspection_fee', 'No charge inspection', '1', 'visit', '0.00'),
            $this->item('other', 'Disposal', '2', 'bag', '15'),
        ]);

        $this->assertSame('120.00', $result['items'][0]['line_total']);
        $this->assertSame(1, $result['items'][0]['sort_order']);
        $this->assertSame('12.62', $result['items'][1]['line_total']);
        $this->assertSame('0.00', $result['items'][2]['line_total']);
        $this->assertSame('30.00', $result['items'][3]['line_total']);
        $this->assertSame('162.62', $result['subtotal']);
        $this->assertSame('162.62', $result['total_amount']);
        $this->assertSame('PHP', $result['currency']);

        $quotation = app(QuotationVersioner::class)->append($booking, [
            $this->item('labor', 'Replace the kitchen trap', '1.50', 'hour', '80.00', [
                'line_total' => '1.00',
            ]),
        ]);

        $this->assertSame('120.00', $quotation->subtotal);
        $this->assertSame('120.00', $quotation->total_amount);
        $this->assertSame('120.00', $quotation->items->first()->line_total);
        $booking->refresh();
        $this->assertSame($financial, $this->financial($booking));
        $this->assertSame('pending', $booking->status);
    }

    public function test_invalid_item_types_quantities_and_prices_are_rejected(): void
    {
        $booking = $this->booking();
        $versioner = app(QuotationVersioner::class);

        foreach ([
            [$this->item('travel', 'Trip', '1', 'visit', '50.00'), 'items.0.item_type'],
            [$this->item('labor', 'Trap', '0', 'hour', '80.00'), 'items.0.quantity'],
            [$this->item('labor', 'Trap', '-1', 'hour', '80.00'), 'items.0.quantity'],
            [$this->item('labor', 'Trap', '1', 'hour', '-5.00'), 'items.0.unit_price'],
            [$this->item('labor', ' ', '1', 'hour', '80.00'), 'items.0.description'],
        ] as [$item, $field]) {
            try {
                $versioner->append($booking, [$item]);
                $this->fail('A quotation accepted an invalid item.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertSame(0, Quotation::query()->count());
        $this->assertSame(0, QuotationItem::query()->count());
    }

    public function test_versions_stay_unique_and_history_is_preserved(): void
    {
        $booking = $this->booking();
        $other = $this->booking();
        $versioner = app(QuotationVersioner::class);

        $first = $versioner->append($booking, [
            $this->item('labor', 'Replace the kitchen trap', '2', 'hour', '100.00'),
        ], null, 'First visit price.');
        $second = $versioner->append($booking, [
            $this->item('material', 'PVC trap', '1', 'piece', '50.00'),
        ]);
        $otherVersion = $versioner->append($other, [
            $this->item('labor', 'Other home', '1', 'hour', '70.00'),
        ]);

        $first->refresh();
        $this->assertSame(1, $first->version_number);
        $this->assertSame(2, $second->version_number);
        $this->assertSame(1, $otherVersion->version_number);
        $this->assertSame('superseded', $first->status);
        $this->assertFalse($first->is_current);
        $this->assertSame('draft', $second->status);
        $this->assertTrue($second->is_current);
        $this->assertNotSame('approved', $second->status);
        $this->assertSame('200.00', $first->total_amount);
        $this->assertSame('200.00', $first->items()->first()->line_total);
        $this->assertSame('Replace the kitchen trap', $first->items()->first()->description);
        $this->assertSame('50.00', $second->total_amount);
        $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->where('is_current', true)->count());

        $duplicate = new Quotation;
        $duplicate->booking_id = $booking->id;
        $duplicate->version_number = 1;
        $duplicate->status = 'draft';
        $duplicate->currency = 'PHP';
        $duplicate->subtotal = '10.00';
        $duplicate->total_amount = '10.00';
        $duplicate->is_current = false;

        try {
            $duplicate->save();
            $this->fail('A booking stored two quotations with version 1.');
        } catch (QueryException) {
            $this->assertSame(2, Quotation::query()->where('booking_id', $booking->id)->count());
        }

        $secondCurrent = new Quotation;
        $secondCurrent->booking_id = $booking->id;
        $secondCurrent->version_number = 3;
        $secondCurrent->status = 'draft';
        $secondCurrent->currency = 'PHP';
        $secondCurrent->subtotal = '10.00';
        $secondCurrent->total_amount = '10.00';
        $secondCurrent->is_current = true;

        try {
            $secondCurrent->save();
            $this->fail('A booking stored two current quotations.');
        } catch (QueryException) {
            $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->where('is_current', true)->count());
        }
    }

    public function test_mass_assignment_cannot_set_calculated_amounts(): void
    {
        $quotation = new Quotation([
            'version_number' => 4,
            'subtotal' => '5.00',
            'total_amount' => '5.00',
            'is_current' => true,
            'submitted_at' => now()->toDateTimeString(),
        ]);
        $item = new QuotationItem([
            'line_total' => '999.00',
        ]);

        $this->assertNull($quotation->version_number);
        $this->assertNull($quotation->subtotal);
        $this->assertNull($quotation->total_amount);
        $this->assertNull($quotation->is_current);
        $this->assertNull($quotation->submitted_at);
        $this->assertNull($item->line_total);
    }

    public function test_customers_and_unrelated_workers_cannot_view_quotations(): void
    {
        $booking = $this->booking();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $otherWorker = User::query()->where('email', 'sample.liza-mercado@fixitdumaguete.test')->firstOrFail();
        $otherCustomer = User::query()->create([
            'name' => 'Other Customer',
            'role' => 'customer',
            'email' => 'other-customer@fixitdumaguete.test',
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('No quotation has been prepared for this visit.');

        app(QuotationVersioner::class)->append($booking, [
            $this->item('labor', 'Replace the kitchen trap', '2', 'hour', '100.00'),
        ], $admin->id, 'First visit price.');

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Version 1')
            ->assertSee('Draft')
            ->assertSee('Current')
            ->assertSee('Replace the kitchen trap')
            ->assertSee('Labor')
            ->assertSee('2.00 hour')
            ->assertSee('₱200.00')
            ->assertSee('First visit price.')
            ->assertSee($admin->name)
            ->assertDontSee('Save draft')
            ->assertDontSee('Approve')
            ->assertDontSee('Reject');

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Quotation')
            ->assertDontSee('Replace the kitchen trap')
            ->assertDontSee('First visit price.');

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Quotation')
            ->assertSee('Replace the kitchen trap')
            ->assertSee('Save draft');

        $this->actingAs($otherCustomer)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();

        $this->actingAs($otherWorker)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function item(string $type, string $description, string $quantity, string $unit, string $unitPrice, array $extra = []): array
    {
        return array_merge([
            'item_type' => $type,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unitPrice,
        ], $extra);
    }

    private function booking(): Booking
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $location = $worker->locations()->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.store', $worker), [
            'service_id' => $worker->services()->firstOrFail()->id,
            'location_id' => $location->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'notes' => 'The kitchen faucet is dripping.',
            'landmark' => 'Blue gate beside the corner store',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => $location->map_latitude,
            'longitude' => $location->map_longitude,
        ])->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function financial(Booking $booking): array
    {
        return $booking->only([
            'price_amount',
            'commission_rate',
            'commission_amount',
            'worker_amount',
            'commission_setting_id',
            'commission_rule',
            'commission_basis',
            'commission_base_amount',
            'commission_original_rate',
            'commission_adjustment',
        ]);
    }
}
