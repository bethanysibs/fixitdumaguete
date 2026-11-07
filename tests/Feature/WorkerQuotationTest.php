<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_an_assigned_worker_can_create_edit_and_submit_a_draft(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Prepare a quotation')
            ->assertSee('Save draft');

        $this->actingAs($worker)
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
                $this->item('material', 'PVC pipe', '2', 'piece', '150.00'),
                $this->item('service_charge', 'Transportation', '1', 'service', '100.00'),
            ], [
                'notes' => 'The trap is under the sink.',
                'line_total' => '1.00',
                'total_amount' => '1.00',
                'status' => 'approved',
                'version_number' => '9',
            ]))
            ->assertRedirect(route('bookings.show', $booking));

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame(1, Quotation::query()->count());
        $this->assertSame(1, $quotation->version_number);
        $this->assertSame('draft', $quotation->status);
        $this->assertTrue($quotation->is_current);
        $this->assertSame($booking->id, $quotation->booking_id);
        $this->assertSame('200.00', $quotation->items[0]->line_total);
        $this->assertSame('300.00', $quotation->items[1]->line_total);
        $this->assertSame('100.00', $quotation->items[2]->line_total);
        $this->assertSame('600.00', $quotation->subtotal);
        $this->assertSame('600.00', $quotation->total_amount);
        $this->assertNull($quotation->submitted_at);
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($worker)
            ->put(route('jobs.quotation.update', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '1', 'hour', '150.00'),
            ]))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame(1, Quotation::query()->count());
        $quotation->refresh();
        $this->assertSame(1, $quotation->version_number);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('150.00', $quotation->total_amount);
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);
        $this->assertSame(1, $quotation->items()->count());

        $this->actingAs($worker)
            ->get(route('jobs.quotation.confirm', $booking))
            ->assertOk()
            ->assertSee('cannot be edited afterward')
            ->assertSee('₱150.00')
            ->assertSee('Submit for admin review');

        $this->actingAs($worker)
            ->post(route('jobs.quotation.submit', $booking), [
                'total_amount' => '1.00',
                'status' => 'approved',
            ])
            ->assertRedirect(route('bookings.show', $booking));

        $quotation->refresh();
        $booking->refresh();
        $this->assertSame('submitted', $quotation->status);
        $this->assertNotNull($quotation->submitted_at);
        $this->assertSame('150.00', $quotation->total_amount);
        $this->assertSame('150.00', $quotation->items()->first()->line_total);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('can no longer be edited');

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Version 1')
            ->assertSee('Submitted')
            ->assertSee('Current')
            ->assertSee('Replace kitchen trap')
            ->assertDontSee('Save draft')
            ->assertDontSee('Approve');
    }

    public function test_a_revision_keeps_the_submitted_quotation_unchanged(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]))->assertRedirect();

        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();

        $submitted = Quotation::query()->firstOrFail();
        $submittedAt = $submitted->submitted_at->toDateTimeString();
        $itemId = $submitted->items()->first()->id;

        $this->actingAs($worker)
            ->put(route('jobs.quotation.update', $booking), $this->payload([
                $this->item('labor', 'Changed after submission', '1', 'hour', '1.00'),
            ]))
            ->assertForbidden();

        $this->actingAs($worker)
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Another version', '1', 'hour', '1.00'),
            ]))
            ->assertForbidden();

        $submitted->refresh();
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('200.00', $submitted->total_amount);
        $this->assertSame('Replace kitchen trap', $submitted->items()->first()->description);
        $this->assertSame($itemId, $submitted->items()->first()->id);
        $this->assertSame($submittedAt, $submitted->submitted_at->toDateTimeString());
        $this->assertSame('pending', $booking->refresh()->status);

        $this->actingAs($worker)
            ->post(route('jobs.quotation.revise', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $submitted->refresh();
        $revision = Quotation::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame('submitted', $submitted->status);
        $this->assertFalse($submitted->is_current);
        $this->assertSame('200.00', $submitted->total_amount);
        $this->assertSame('Replace kitchen trap', $submitted->items()->first()->description);
        $this->assertSame($itemId, $submitted->items()->first()->id);
        $this->assertSame($submittedAt, $submitted->submitted_at->toDateTimeString());
        $this->assertSame(2, $revision->version_number);
        $this->assertSame('draft', $revision->status);
        $this->assertTrue($revision->is_current);
        $this->assertSame('200.00', $revision->total_amount);

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('material', 'PVC pipe', '2', 'piece', '150.00'),
        ]))->assertRedirect();

        $submitted->refresh();
        $revision->refresh();
        $this->assertSame('Replace kitchen trap', $submitted->items()->first()->description);
        $this->assertSame('200.00', $submitted->total_amount);
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('PVC pipe', $revision->items()->first()->description);
        $this->assertSame('300.00', $revision->total_amount);
        $this->assertSame(2, Quotation::query()->count());
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));
    }

    public function test_customers_admins_and_other_workers_cannot_manage_the_quotation(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $otherWorker = User::query()->where('email', 'sample.liza-mercado@fixitdumaguete.test')->firstOrFail();
        $payload = $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]);

        $this->actingAs($customer)->post(route('jobs.quotation.store', $booking), $payload)->assertForbidden();
        $this->actingAs($customer)->get(route('jobs.quotation.confirm', $booking))->assertForbidden();
        $this->actingAs($customer)->post(route('jobs.quotation.submit', $booking))->assertForbidden();
        $this->actingAs($customer)->post(route('jobs.quotation.revise', $booking))->assertForbidden();
        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Quotation')
            ->assertDontSee('Save draft');

        $this->actingAs($admin)->post(route('jobs.quotation.store', $booking), $payload)->assertForbidden();
        $this->actingAs($otherWorker)->post(route('jobs.quotation.store', $booking), $payload)->assertForbidden();
        $this->actingAs($otherWorker)->get(route('bookings.show', $booking))->assertForbidden();

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $payload)->assertRedirect();

        $this->actingAs($customer)->put(route('jobs.quotation.update', $booking), $payload)->assertForbidden();
        $this->actingAs($otherWorker)->put(route('jobs.quotation.update', $booking), $payload)->assertForbidden();
        $this->actingAs($otherWorker)->get(route('jobs.quotation.confirm', $booking))->assertForbidden();
        $this->actingAs($otherWorker)->post(route('jobs.quotation.submit', $booking))->assertForbidden();
        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Replace kitchen trap');

        $this->assertSame(1, Quotation::query()->count());
        $this->assertSame('draft', Quotation::query()->first()->status);
    }

    public function test_invalid_quantities_and_prices_do_not_change_a_quotation(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($worker)
            ->from(route('bookings.show', $booking))
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '2', 'hour', '-5.00'),
            ]))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHasErrors('items.0.unit_price');

        $this->actingAs($worker)
            ->from(route('bookings.show', $booking))
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '0', 'hour', '100.00'),
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->actingAs($worker)
            ->from(route('bookings.show', $booking))
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '-1', 'hour', '100.00'),
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(0, Quotation::query()->count());

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]))->assertRedirect();

        $this->actingAs($worker)
            ->from(route('bookings.show', $booking))
            ->put(route('jobs.quotation.update', $booking), $this->payload([
                $this->item('labor', 'Changed trap', '1', 'hour', '-10'),
            ]))
            ->assertSessionHasErrors('items.0.unit_price');

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('pending', $booking->refresh()->status);
    }

    public function test_pending_confirmed_and_in_progress_bookings_can_be_quoted(): void
    {
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        foreach (['pending', 'confirmed', 'in_progress'] as $status) {
            $booking = $this->booking();
            $booking->update(['status' => $status]);

            $this->actingAs($worker)
                ->post(route('jobs.quotation.store', $booking), $this->payload([
                    $this->item('labor', 'Replace kitchen trap', '1', 'hour', '100.00'),
                ]))
                ->assertRedirect(route('bookings.show', $booking));

            $this->assertSame($status, $booking->refresh()->status);
            $this->assertSame('draft', $booking->currentQuotation->status);
        }
    }

    public function test_a_canceled_booking_keeps_its_quotation_and_rejects_changes(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $payload = $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]);

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $payload)->assertRedirect();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();
        $booking->update(['status' => 'canceled']);

        $quotation = $booking->currentQuotation()->firstOrFail();
        $submittedAt = $quotation->submitted_at->toDateTimeString();

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $payload)->assertForbidden();
        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $payload)->assertForbidden();
        $this->actingAs($worker)->get(route('jobs.quotation.confirm', $booking))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking))->assertForbidden();

        $quotation->refresh();
        $booking->refresh();
        $this->assertSame('canceled', $booking->status);
        $this->assertSame($financial, $this->financial($booking));
        $this->assertSame('submitted', $quotation->status);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);
        $this->assertSame($submittedAt, $quotation->submitted_at->toDateTimeString());
        $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->count());

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('This visit is canceled, so the quotation cannot be changed.')
            ->assertSee('Replace kitchen trap')
            ->assertSee('Submitted')
            ->assertDontSee('Save draft')
            ->assertDontSee('Prepare a revision');

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Replace kitchen trap')
            ->assertSee('Submitted');
    }

    public function test_a_completed_booking_cannot_gain_or_submit_a_quotation(): void
    {
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $closed = $this->booking();
        $closed->update(['status' => 'completed']);
        $financial = $this->financial($closed);

        $this->actingAs($worker)
            ->post(route('jobs.quotation.store', $closed), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
            ]))
            ->assertForbidden();

        $this->assertSame(0, $closed->quotations()->count());
        $this->assertSame('completed', $closed->refresh()->status);
        $this->assertSame($financial, $this->financial($closed));

        $this->actingAs($worker)
            ->get(route('bookings.show', $closed))
            ->assertOk()
            ->assertSee('This visit is done, so a new quotation cannot be prepared or submitted.')
            ->assertDontSee('Save draft');

        $draftBooking = $this->booking();
        $this->actingAs($worker)->post(route('jobs.quotation.store', $draftBooking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]))->assertRedirect();
        $draftBooking->update(['status' => 'completed']);

        $this->actingAs($worker)->get(route('jobs.quotation.confirm', $draftBooking))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $draftBooking))->assertForbidden();
        $this->actingAs($worker)
            ->put(route('jobs.quotation.update', $draftBooking), $this->payload([
                $this->item('material', 'PVC pipe', '1', 'piece', '50.00'),
            ]))
            ->assertRedirect(route('bookings.show', $draftBooking));

        $draft = $draftBooking->currentQuotation()->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('50.00', $draft->total_amount);
        $this->assertSame('PVC pipe', $draft->items()->first()->description);
        $this->assertSame('completed', $draftBooking->refresh()->status);

        $submittedBooking = $this->booking();
        $this->actingAs($worker)->post(route('jobs.quotation.store', $submittedBooking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]))->assertRedirect();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $submittedBooking))->assertRedirect();
        $submittedBooking->update(['status' => 'completed']);
        $submitted = $submittedBooking->currentQuotation()->firstOrFail();
        $submittedAt = $submitted->submitted_at->toDateTimeString();

        $this->actingAs($worker)->post(route('jobs.quotation.revise', $submittedBooking))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $submittedBooking))->assertForbidden();

        $submitted->refresh();
        $this->assertSame('completed', $submittedBooking->refresh()->status);
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame('200.00', $submitted->total_amount);
        $this->assertSame('Replace kitchen trap', $submitted->items()->first()->description);
        $this->assertSame($submittedAt, $submitted->submitted_at->toDateTimeString());
        $this->assertSame(1, Quotation::query()->where('booking_id', $submittedBooking->id)->count());
    }

    /**
     * @param  list<array<string, string>>  $items
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $items, array $extra = []): array
    {
        if (isset($extra['line_total'])) {
            $items[0]['line_total'] = $extra['line_total'];
            unset($extra['line_total']);
        }

        return array_merge([
            'items' => $items,
            'notes' => 'The trap is under the sink.',
        ], $extra);
    }

    /**
     * @return array<string, string>
     */
    private function item(string $type, string $description, string $quantity, string $unit, string $unitPrice): array
    {
        return [
            'item_type' => $type,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $unitPrice,
        ];
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
