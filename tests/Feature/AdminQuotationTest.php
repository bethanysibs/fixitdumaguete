<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_an_admin_can_review_a_submitted_quotation(): void
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($worker)
            ->post(route('jobs.quotation.store', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '2', 'hour', '100.00'),
            ], [
                'notes' => 'The trap is under the sink.',
                'admin_notes' => 'Internal gate code 4412',
                'rejection_reason' => 'Too high',
                'status' => 'approved',
                'total_amount' => '1.00',
            ]))
            ->assertRedirect();

        $draft = $booking->currentQuotation()->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->admin_notes);
        $this->assertNull($draft->rejection_reason);
        $this->assertFalse($draft->is_admin_adjustment);

        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Review quotation')
            ->assertDontSee('Approve for customer review');

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee($booking->workerProfile->user->name)
            ->assertSee($booking->customer->name)
            ->assertSee('Replace kitchen trap')
            ->assertSee('Labor')
            ->assertSee('₱200.00')
            ->assertSee('Version 1')
            ->assertSee('Submitted')
            ->assertSee('Booked price')
            ->assertSee('Approve for customer review')
            ->assertSee('Reject quotation')
            ->assertSee('Internal note');
    }

    public function test_non_admins_cannot_use_admin_quotation_routes(): void
    {
        $booking = $this->submitQuotation();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $payload = $this->payload([
            $this->item('labor', 'Replace kitchen trap', '1', 'hour', '50.00'),
        ]);

        foreach ([$worker, $customer] as $user) {
            $this->actingAs($user)->get(route('admin.quotations.show', $booking))->assertForbidden();
            $this->actingAs($user)->put(route('admin.quotations.update', $booking), $payload)->assertForbidden();
            $this->actingAs($user)->get(route('admin.quotations.confirm', $booking))->assertForbidden();
            $this->actingAs($user)->post(route('admin.quotations.approve', $booking))->assertForbidden();
            $this->actingAs($user)->post(route('admin.quotations.reject', $booking), [
                'rejection_reason' => 'Too high',
            ])->assertForbidden();
        }

        auth()->logout();

        $this->get(route('admin.quotations.show', $booking))->assertRedirect(route('login'));
        $this->assertSame('submitted', $booking->currentQuotation()->firstOrFail()->status);
        $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->count());
    }

    public function test_an_admin_adjustment_keeps_the_worker_quotation(): void
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $original = $booking->currentQuotation()->firstOrFail();
        $itemId = $original->items()->first()->id;
        $submittedAt = $original->submitted_at->toDateTimeString();

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload([
                $this->item('material', 'PVC pipe', '1', 'piece', '50.00'),
            ], [
                'notes' => 'Updated scope.',
                'admin_notes' => 'Check the pipe.',
                'total_amount' => '1.00',
                'status' => 'approved',
            ]))
            ->assertRedirect(route('admin.quotations.show', $booking));

        $original->refresh();
        $adjusted = Quotation::query()->where('version_number', 2)->firstOrFail();
        $booking->refresh();

        $this->assertSame(2, Quotation::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('submitted', $original->status);
        $this->assertFalse($original->is_current);
        $this->assertFalse($original->is_admin_adjustment);
        $this->assertSame('200.00', $original->total_amount);
        $this->assertSame('The trap is under the sink.', $original->notes);
        $this->assertNull($original->admin_notes);
        $this->assertSame('Replace kitchen trap', $original->items()->first()->description);
        $this->assertSame($itemId, $original->items()->first()->id);
        $this->assertSame($submittedAt, $original->submitted_at->toDateTimeString());
        $this->assertSame($worker->id, $original->created_by);

        $this->assertSame('submitted', $adjusted->status);
        $this->assertTrue($adjusted->is_current);
        $this->assertTrue($adjusted->is_admin_adjustment);
        $this->assertSame('50.00', $adjusted->total_amount);
        $this->assertSame('50.00', $adjusted->items()->first()->line_total);
        $this->assertSame('Updated scope.', $adjusted->notes);
        $this->assertSame('Check the pipe.', $adjusted->admin_notes);
        $this->assertSame($admin->id, $adjusted->created_by);
        $this->assertSame($original->id, $adjusted->source_quotation_id);
        $this->assertNull($adjusted->admin_approved_at);
        $this->assertNotSame('approved', $adjusted->status);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('An administrator adjusted this quotation')
            ->assertSee('under review')
            ->assertSee('Admin adjustment')
            ->assertSee('Updated scope.')
            ->assertDontSee('Check the pipe.')
            ->assertDontSee('Save draft');

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Changed by the worker', '1', 'hour', '10.00'),
        ]))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking))->assertForbidden();

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Check the pipe.')
            ->assertDontSee('Updated scope.')
            ->assertDontSee('PVC pipe');

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload([
                $this->item('material', 'PVC pipe', '1', 'piece', '50.00'),
            ], [
                'notes' => 'Different customer note.',
                'admin_notes' => 'Check the pipe.',
            ]))
            ->assertRedirect(route('admin.quotations.show', $booking));

        $adjusted->refresh();
        $noteVersion = Quotation::query()->where('version_number', 3)->firstOrFail();
        $this->assertSame('Updated scope.', $adjusted->notes);
        $this->assertSame('50.00', $adjusted->total_amount);
        $this->assertFalse($adjusted->is_current);
        $this->assertSame('Different customer note.', $noteVersion->notes);
        $this->assertSame('50.00', $noteVersion->total_amount);
        $this->assertSame('submitted', $noteVersion->status);
        $this->assertNull($noteVersion->admin_approved_at);
        $this->assertSame($adjusted->id, $noteVersion->source_quotation_id);

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload([
                $this->item('material', 'PVC pipe', '0', 'piece', '50.00'),
            ]))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(3, Quotation::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('50.00', $noteVersion->refresh()->total_amount);
    }

    public function test_an_internal_note_does_not_create_a_version_and_stays_private(): void
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $quotation = $booking->currentQuotation()->with('items')->firstOrFail();
        $submittedAt = $quotation->submitted_at->toDateTimeString();

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload(
                $this->storedItems($quotation),
                [
                    'notes' => 'The trap is under the sink.',
                    'admin_notes' => 'Internal gate code 4412',
                ],
            ))
            ->assertRedirect(route('admin.quotations.show', $booking))
            ->assertSessionHas('success', 'The internal note was saved. The quotation version was kept.');

        $quotation->refresh();
        $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('submitted', $quotation->status);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('Internal gate code 4412', $quotation->admin_notes);
        $this->assertSame($submittedAt, $quotation->submitted_at->toDateTimeString());
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee('Internal gate code 4412');

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Internal gate code 4412');

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Internal gate code 4412')
            ->assertDontSee('Quotation');
    }

    public function test_an_admin_can_approve_a_submitted_quotation_for_customer_review(): void
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $quotation = $booking->currentQuotation()->with('items')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.quotations.confirm', $booking))
            ->assertOk()
            ->assertSee('does not mean the customer accepted the price')
            ->assertSee('₱200.00')
            ->assertSee('Approve for customer review');

        $this->actingAs($admin)
            ->post(route('admin.quotations.approve', $booking))
            ->assertRedirect(route('admin.quotations.show', $booking));

        $quotation->refresh();
        $booking->refresh();
        $approvedAt = $quotation->admin_approved_at->toDateTimeString();
        $this->assertSame('admin_approved', $quotation->status);
        $this->assertNotSame('approved', $quotation->status);
        $this->assertSame($admin->id, $quotation->admin_approved_by);
        $this->assertNotNull($quotation->admin_approved_at);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.quotations.reject', $booking), [
            'rejection_reason' => 'Changed our mind',
        ])->assertForbidden();

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Approved for customer review')
            ->assertSee($admin->name)
            ->assertDontSee('Save draft')
            ->assertDontSee('Prepare a revision');

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Changed after approval', '1', 'hour', '10.00'),
        ]))->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking))->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload($this->storedItems($quotation), [
                'notes' => 'The trap is under the sink.',
                'admin_notes' => 'Caller asked for a morning visit.',
            ]))
            ->assertRedirect(route('admin.quotations.show', $booking));

        $quotation->refresh();
        $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('admin_approved', $quotation->status);
        $this->assertSame($approvedAt, $quotation->admin_approved_at->toDateTimeString());
        $this->assertSame('Caller asked for a morning visit.', $quotation->admin_notes);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame('pending', $booking->refresh()->status);
    }

    public function test_changing_an_approved_quotation_requires_a_new_version_and_reapproval(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 10:00:00'));
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $approved = $booking->currentQuotation()->firstOrFail();
        $approvedAt = $approved->admin_approved_at->toDateTimeString();
        $itemId = $approved->items()->first()->id;

        $this->actingAs($admin)
            ->put(route('admin.quotations.update', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '1', 'hour', '80.00'),
            ], [
                'notes' => 'The trap is under the sink.',
                'total_amount' => '1.00',
                'status' => 'admin_approved',
            ]))
            ->assertRedirect(route('admin.quotations.show', $booking));

        $approved->refresh();
        $replacement = Quotation::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame('admin_approved', $approved->status);
        $this->assertFalse($approved->is_current);
        $this->assertSame('200.00', $approved->total_amount);
        $this->assertSame($itemId, $approved->items()->first()->id);
        $this->assertSame($approvedAt, $approved->admin_approved_at->toDateTimeString());
        $this->assertSame($admin->id, $approved->admin_approved_by);

        $this->assertSame('submitted', $replacement->status);
        $this->assertTrue($replacement->is_current);
        $this->assertTrue($replacement->is_admin_adjustment);
        $this->assertSame('80.00', $replacement->total_amount);
        $this->assertNull($replacement->admin_approved_at);
        $this->assertNull($replacement->admin_approved_by);
        $this->assertSame($approved->id, $replacement->source_quotation_id);
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->travel(1)->minutes();
        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();

        $approved->refresh();
        $replacement->refresh();
        $this->assertSame($approvedAt, $approved->admin_approved_at->toDateTimeString());
        $this->assertSame('admin_approved', $replacement->status);
        $this->assertSame($admin->id, $replacement->admin_approved_by);
        $this->assertNotNull($replacement->admin_approved_at);
        $this->assertNotSame($approvedAt, $replacement->admin_approved_at->toDateTimeString());
    }

    public function test_rejection_requires_a_reason_and_the_worker_can_revise(): void
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $quotation = $booking->currentQuotation()->firstOrFail();
        $submittedAt = $quotation->submitted_at->toDateTimeString();
        $itemId = $quotation->items()->first()->id;

        $this->actingAs($admin)
            ->post(route('admin.quotations.reject', $booking), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $quotation->refresh();
        $this->assertSame('submitted', $quotation->status);
        $this->assertNull($quotation->rejection_reason);

        $this->actingAs($admin)
            ->post(route('admin.quotations.reject', $booking), [
                'rejection_reason' => 'The labor hours are too high.',
                'admin_notes' => 'Staff follow-up 7781',
            ])
            ->assertRedirect(route('admin.quotations.show', $booking));

        $quotation->refresh();
        $this->assertSame('rejected', $quotation->status);
        $this->assertSame($admin->id, $quotation->rejected_by);
        $this->assertNotNull($quotation->rejected_at);
        $this->assertSame('The labor hours are too high.', $quotation->rejection_reason);
        $this->assertSame('Staff follow-up 7781', $quotation->admin_notes);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame($itemId, $quotation->items()->first()->id);
        $this->assertSame($submittedAt, $quotation->submitted_at->toDateTimeString());
        $this->assertSame('pending', $booking->refresh()->status);
        $this->assertSame($financial, $this->financial($booking));

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('The labor hours are too high.')
            ->assertSee($admin->name)
            ->assertDontSee('Staff follow-up 7781')
            ->assertDontSee('Save draft');

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('The labor hours are too high.')
            ->assertDontSee('Staff follow-up 7781');

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee('Staff follow-up 7781');

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Changed after rejection', '1', 'hour', '10.00'),
        ]))->assertForbidden();

        $this->actingAs($worker)
            ->post(route('jobs.quotation.revise', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $quotation->refresh();
        $revision = Quotation::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame('rejected', $quotation->status);
        $this->assertFalse($quotation->is_current);
        $this->assertSame('The labor hours are too high.', $quotation->rejection_reason);
        $this->assertSame('200.00', $quotation->total_amount);
        $this->assertSame($itemId, $quotation->items()->first()->id);
        $this->assertSame('draft', $revision->status);
        $this->assertTrue($revision->is_current);
        $this->assertFalse($revision->is_admin_adjustment);
        $this->assertNull($revision->rejection_reason);
        $this->assertNull($revision->rejected_at);
        $this->assertNull($revision->admin_approved_at);
        $this->assertNull($revision->admin_notes);
        $this->assertNull($revision->submitted_at);
        $this->assertSame($quotation->id, $revision->source_quotation_id);
        $this->assertSame('200.00', $revision->total_amount);

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), $this->payload([
            $this->item('labor', 'Shorter repair', '1', 'hour', '80.00'),
        ]))->assertRedirect();

        $quotation->refresh();
        $revision->refresh();
        $this->assertSame('Replace kitchen trap', $quotation->items()->first()->description);
        $this->assertSame('rejected', $quotation->status);
        $this->assertSame('Shorter repair', $revision->items()->first()->description);
        $this->assertSame('draft', $revision->status);
        $this->assertNull($revision->rejection_reason);
    }

    public function test_canceled_and_completed_bookings_cannot_receive_quotation_decisions(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        foreach (['canceled', 'completed'] as $status) {
            $booking = $this->submitQuotation();
            $financial = $this->financial($booking);
            $booking->update(['status' => $status]);
            $quotation = $booking->currentQuotation()->firstOrFail();
            $submittedAt = $quotation->submitted_at->toDateTimeString();

            $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertForbidden();
            $this->actingAs($admin)->get(route('admin.quotations.confirm', $booking))->assertForbidden();
            $this->actingAs($admin)->post(route('admin.quotations.reject', $booking), [
                'rejection_reason' => 'Too high',
            ])->assertForbidden();
            $this->actingAs($admin)->put(route('admin.quotations.update', $booking), $this->payload([
                $this->item('labor', 'Replace kitchen trap', '1', 'hour', '50.00'),
            ]))->assertForbidden();

            $quotation->refresh();
            $booking->refresh();
            $this->assertSame($status, $booking->status);
            $this->assertSame($financial, $this->financial($booking));
            $this->assertSame('submitted', $quotation->status);
            $this->assertSame('200.00', $quotation->total_amount);
            $this->assertSame($submittedAt, $quotation->submitted_at->toDateTimeString());
            $this->assertNull($quotation->admin_approved_at);
            $this->assertNull($quotation->rejection_reason);
            $this->assertSame(1, Quotation::query()->where('booking_id', $booking->id)->count());

            $this->actingAs($admin)
                ->get(route('admin.quotations.show', $booking))
                ->assertOk()
                ->assertSee('Replace kitchen trap')
                ->assertSee($status === 'canceled'
                    ? 'This visit is canceled, so the quotation cannot be changed.'
                    : 'This visit is done, so the quotation cannot be adjusted or approved.')
                ->assertDontSee('Save adjustment')
                ->assertDontSee('Approve for customer review');
        }
    }

    public function test_confirmed_and_in_progress_bookings_can_be_approved_without_changing_status(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        foreach (['confirmed', 'in_progress'] as $status) {
            $booking = $this->submitQuotation();
            $financial = $this->financial($booking);
            $booking->update(['status' => $status]);

            $this->actingAs($admin)
                ->post(route('admin.quotations.approve', $booking))
                ->assertRedirect(route('admin.quotations.show', $booking));

            $quotation = $booking->currentQuotation()->firstOrFail();
            $this->assertSame('admin_approved', $quotation->status);
            $this->assertSame($status, $booking->refresh()->status);
            $this->assertSame($financial, $this->financial($booking));
        }
    }

    /**
     * @param  list<array<string, string>>  $items
     * @param  array<string, string>  $extra
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

    /**
     * @return list<array<string, string>>
     */
    private function storedItems(Quotation $quotation): array
    {
        return $quotation->items->map(fn ($item) => $this->item(
            $item->item_type,
            $item->description,
            (string) $item->quantity,
            $item->unit,
            (string) $item->unit_price,
        ))->all();
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
