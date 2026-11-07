<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_a_customer_can_review_only_an_admin_approved_quotation(): void
    {
        $booking = $this->booking();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $other = User::query()->create([
            'name' => 'Other Customer',
            'role' => 'customer',
            'email' => 'other-customer@fixitdumaguete.test',
            'password' => 'password',
        ]);

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
            $this->item('material', 'PVC pipe', '1', 'piece', '40.00'),
            $this->item('service_charge', 'Transportation', '1', 'service', '20.00'),
            $this->item('inspection_fee', 'Inspection', '1', 'visit', '15.00'),
            $this->item('other', 'Disposal', '1', 'bag', '10.00'),
        ], ['notes' => 'The trap is under the sink.']))->assertRedirect();

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('A quotation is not ready for you to review yet.')
            ->assertDontSee('Replace kitchen trap');

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 1,
        ])->assertForbidden();

        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertDontSee('Replace kitchen trap');
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 1,
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $quotation = $booking->currentQuotation()->firstOrFail();
        $quotation->admin_notes = 'Internal gate code 4412';
        $quotation->save();

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Review the quotation')
            ->assertDontSee('Internal gate code 4412')
            ->assertDontSee('Replace kitchen trap');

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('Version 1')
            ->assertSee('Ready for your review')
            ->assertSee('Replace kitchen trap')
            ->assertSee('Labor')
            ->assertSee('Material')
            ->assertSee('Service charge')
            ->assertSee('Inspection fee')
            ->assertSee('Other')
            ->assertSee('₱285.00')
            ->assertSee('The trap is under the sink.')
            ->assertSee('Accept quotation')
            ->assertSee('Decline quotation')
            ->assertDontSee('Internal gate code 4412')
            ->assertDontSee($admin->name);

        $this->actingAs($other)->get(route('bookings.quotation.show', $booking))->assertForbidden();
        $this->actingAs($worker)->get(route('bookings.quotation.show', $booking))->assertForbidden();
        $this->actingAs($admin)->get(route('bookings.quotation.show', $booking))->assertForbidden();
        $this->actingAs($worker)->post(route('bookings.quotation.accept', $booking), ['version_number' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('bookings.quotation.accept', $booking), ['version_number' => 1])->assertForbidden();
        $this->actingAs($other)->post(route('bookings.quotation.accept', $booking), ['version_number' => 1])->assertForbidden();

        auth()->logout();
        $this->get(route('bookings.quotation.show', $booking))->assertRedirect(route('login'));

        $quotation->refresh();
        $this->assertSame('admin_approved', $quotation->status);
        $this->assertNull($quotation->customer_approved_at);
    }

    public function test_a_customer_can_accept_the_exact_admin_approved_version(): void
    {
        $booking = $this->readyQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $payments = Payment::query()->count();
        $quotation = $booking->currentQuotation()->firstOrFail();
        $itemId = $quotation->items()->first()->id;

        $this->actingAs($customer)
            ->get(route('bookings.quotation.confirm', $booking))
            ->assertOk()
            ->assertSee('You are accepting version 1 for ₱200.00')
            ->assertSee('does not record a payment');

        $this->travelTo(Carbon::parse('2026-11-07 14:00:00'));
        $this->actingAs($customer)
            ->post(route('bookings.quotation.accept', $booking), [
                'version_number' => 1,
                'total_amount' => '1.00',
                'status' => 'customer_rejected',
            ])
            ->assertRedirect(route('bookings.quotation.show', $booking));

        $quotation->refresh();
        $booking->refresh();
        $acceptedAt = $quotation->customer_approved_at->toDateTimeString();
        $this->assertSame('approved', $quotation->status);
        $this->assertSame($customer->id, $quotation->customer_approved_by);
        $this->assertNotNull($quotation->customer_approved_at);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame($itemId, $quotation->items()->first()->id);
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($financial, $this->financial($booking));
        $this->assertSame($payments, Payment::query()->count());

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 1,
        ])->assertForbidden();
        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'version_number' => 1,
            'customer_rejection_reason' => 'Changed my mind',
        ])->assertForbidden();
        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Changed after acceptance', '1', 'hour', '10.00'),
        ]))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking))->assertForbidden();

        $quotation->refresh();
        $this->assertSame('approved', $quotation->status);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame($acceptedAt, $quotation->customer_approved_at->toDateTimeString());

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('Accepted')
            ->assertDontSee('Accept quotation');

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('The customer accepted this quotation')
            ->assertSee('Accepted by the customer')
            ->assertSee($customer->name);

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee('The customer accepted this quotation')
            ->assertSee('Accepted by the customer')
            ->assertSee('₱200.00');
    }

    public function test_a_customer_cannot_accept_a_quotation_that_is_not_ready(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        $draft = $this->booking();
        $this->actingAs($worker)->post(route('jobs.quotation.store', $draft), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ]))->assertRedirect();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $draft), [
            'version_number' => 1,
        ])->assertForbidden();

        $submitted = $this->submitQuotation();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $submitted), [
            'version_number' => 1,
        ])->assertForbidden();

        $rejected = $this->submitQuotation();
        $this->actingAs($admin)->post(route('admin.quotations.reject', $rejected), [
            'rejection_reason' => 'The labor hours are too high.',
        ])->assertRedirect();
        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $rejected))
            ->assertOk()
            ->assertDontSee('The labor hours are too high.')
            ->assertDontSee('Replace kitchen trap');
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $rejected), [
            'version_number' => 1,
        ])->assertForbidden();

        $superseded = $this->readyQuotation();
        $superseded->currentQuotation()->firstOrFail()->update(['status' => 'superseded']);
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $superseded), [
            'version_number' => 1,
        ])->assertForbidden();
        $this->assertSame('superseded', $superseded->currentQuotation()->firstOrFail()->status);

        $stale = $this->readyQuotation();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $stale), [
            'version_number' => 9,
            'total_amount' => '1.00',
        ])->assertSessionHasErrors('version_number');
        $this->assertSame('admin_approved', $stale->currentQuotation()->firstOrFail()->status);
        $this->assertSame('200.00', $stale->currentQuotation()->firstOrFail()->total_amount);

        $closed = $this->readyQuotation();
        $closed->update(['status' => 'canceled']);
        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $closed))
            ->assertOk()
            ->assertSee('Replace kitchen trap')
            ->assertSee('This visit is canceled');
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $closed), [
            'version_number' => 1,
        ])->assertForbidden();
        $this->assertSame('canceled', $closed->refresh()->status);
        $this->assertSame('admin_approved', $closed->currentQuotation()->firstOrFail()->status);
    }

    public function test_a_customer_can_decline_an_admin_approved_quotation(): void
    {
        $booking = $this->readyQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $quotation = $booking->currentQuotation()->firstOrFail();
        $quotation->admin_notes = 'Internal gate code 4412';
        $quotation->save();
        $itemId = $quotation->items()->first()->id;
        $submittedAt = $quotation->submitted_at->toDateTimeString();

        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'version_number' => 1,
            'customer_rejection_reason' => '',
        ])->assertSessionHasErrors('customer_rejection_reason');
        $this->assertSame('admin_approved', $quotation->refresh()->status);

        $submitted = $this->submitQuotation();
        $this->actingAs($customer)->post(route('bookings.quotation.reject', $submitted), [
            'version_number' => 1,
            'customer_rejection_reason' => 'Too expensive for this repair.',
        ])->assertForbidden();

        $this->travelTo(Carbon::parse('2026-11-07 15:00:00'));
        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'version_number' => 1,
            'customer_rejection_reason' => 'Too expensive for this repair.',
            'total_amount' => '1.00',
        ])->assertRedirect(route('bookings.quotation.show', $booking));

        $quotation->refresh();
        $booking->refresh();
        $this->assertSame('customer_rejected', $quotation->status);
        $this->assertSame($customer->id, $quotation->customer_rejected_by);
        $this->assertNotNull($quotation->customer_rejected_at);
        $this->assertSame('Too expensive for this repair.', $quotation->customer_rejection_reason);
        $this->assertNull($quotation->rejection_reason);
        $this->assertNull($quotation->rejected_by);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame($itemId, $quotation->items()->first()->id);
        $this->assertSame($submittedAt, $quotation->submitted_at->toDateTimeString());
        $this->assertSame('pending', $booking->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('Declined')
            ->assertSee('Too expensive for this repair.')
            ->assertDontSee('Internal gate code 4412')
            ->assertDontSee('Decline quotation');

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('The customer declined this quotation')
            ->assertSee('Too expensive for this repair.')
            ->assertSee($customer->name)
            ->assertDontSee('Internal gate code 4412');

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee('The customer declined this quotation')
            ->assertSee('Too expensive for this repair.')
            ->assertSee('Internal gate code 4412');

        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'version_number' => 1,
            'customer_rejection_reason' => 'Again',
        ])->assertForbidden();
        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Changed after decline', '1', 'hour', '10.00'),
        ]))->assertForbidden();

        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking))->assertRedirect();
        $quotation->refresh();
        $revision = Quotation::query()->where('booking_id', $booking->id)->where('version_number', 2)->firstOrFail();
        $this->assertSame('customer_rejected', $quotation->status);
        $this->assertFalse($quotation->is_current);
        $this->assertSame('Too expensive for this repair.', $quotation->customer_rejection_reason);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->customer_rejection_reason);
        $this->assertNull($revision->customer_approved_at);
        $this->assertSame($quotation->id, $revision->source_quotation_id);

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('A new quotation is not ready for you to review yet.')
            ->assertSee('Too expensive for this repair.')
            ->assertDontSee('Accept quotation');
    }

    public function test_a_revised_quotation_needs_a_new_admin_and_customer_decision(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 16:00:00'));
        $booking = $this->readyQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 1,
        ])->assertRedirect();
        $accepted = $booking->currentQuotation()->firstOrFail();
        $acceptedAt = $accepted->customer_approved_at->toDateTimeString();
        $itemId = $accepted->items()->first()->id;

        $this->travel(1)->hour();
        $this->actingAs($admin)->put(route('admin.quotations.update', $booking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '1', 'hour', '80.00'),
        ], [
            'notes' => 'The trap is under the sink.',
            'total_amount' => '1.00',
            'status' => 'approved',
        ]))->assertRedirect();

        $accepted->refresh();
        $replacement = Quotation::query()->where('booking_id', $booking->id)->where('version_number', 2)->firstOrFail();
        $this->assertSame('approved', $accepted->status);
        $this->assertFalse($accepted->is_current);
        $this->assertSame('200.00', $accepted->total_amount);
        $this->assertSame($itemId, $accepted->items()->first()->id);
        $this->assertSame($acceptedAt, $accepted->customer_approved_at->toDateTimeString());
        $this->assertSame($customer->id, $accepted->customer_approved_by);
        $this->assertSame('submitted', $replacement->status);
        $this->assertTrue($replacement->is_admin_adjustment);
        $this->assertNull($replacement->customer_approved_at);
        $this->assertNull($replacement->customer_approved_by);
        $this->assertSame('80.00', $replacement->total_amount);
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 2,
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('Ready for your review')
            ->assertSee('Accepted');

        $this->travel(1)->hour();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 2,
        ])->assertRedirect();

        $accepted->refresh();
        $replacement->refresh();
        $this->assertSame($acceptedAt, $accepted->customer_approved_at->toDateTimeString());
        $this->assertSame('200.00', $accepted->total_amount);
        $this->assertSame('approved', $replacement->status);
        $this->assertSame('80.00', $replacement->total_amount);
        $this->assertSame($customer->id, $replacement->customer_approved_by);
        $this->assertNotSame($acceptedAt, $replacement->customer_approved_at->toDateTimeString());
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));
    }

    /**
     * @param  list<array<string, string>>  $items
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $items, array $extra = []): array
    {
        return array_merge(['items' => $items], $extra);
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

    private function readyQuotation(): Booking
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();

        return $booking->refresh();
    }

    private function submitQuotation(): Booking
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), $this->payload([
            $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
        ], [
            'notes' => 'The trap is under the sink.',
        ]))->assertRedirect();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();

        return $booking->refresh();
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
