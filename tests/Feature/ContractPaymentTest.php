<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\PaymentCalculator;
use App\Services\PaymentSettingService;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
        Storage::fake('local');
    }

    public function test_payments_follow_the_whole_contract_and_only_verified_receipts_reduce_it(): void
    {
        $this->percentage('10.00');
        $booking = $this->acceptedQuotation();
        $price = (string) $booking->price_amount;
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        $this->secondQuotation($booking, '350.00');
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(140000, $summary['contract_cents']);
        $this->assertSame(14000, $summary['required_deposit_cents']);
        $this->assertSame(8500, $summary['original_deposit_cents']);
        $this->assertSame(5500, $summary['additional_deposit_cents']);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => '850.00',
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['verified_cents']);
        $this->assertSame(85000, $summary['recorded_cents']);
        $this->assertSame(140000, $summary['balance_outstanding_cents']);

        $this->actingAs($customer)->post(route('bookings.payments.store', $booking), $this->claim([
            'amount' => '20.00',
            'payment_reference' => 'WAIT 1001',
        ]))->assertRedirect();
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['verified_cents']);
        $this->assertSame(2000, $summary['pending_cents']);
        $this->assertSame(140000, $summary['balance_outstanding_cents']);
        $this->assertSame(138000, $summary['eligible_cents']);

        $claim = Payment::query()->where('payment_reference', 'WAIT 1001')->firstOrFail();
        $this->actingAs($worker)->post(route('admin.payments.verify', $claim))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.payments.verify', $claim))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.payments.verify', $claim))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.payments.verify', $claim))->assertSessionHasErrors('payment');

        $claim->refresh();
        $this->assertSame($admin->id, $claim->verified_by);
        $this->assertNotNull($claim->verified_at);
        $this->assertSame('verified', $claim->status);
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(2000, $summary['verified_cents']);
        $this->assertSame(138000, $summary['balance_outstanding_cents']);
        $this->assertSame($price, $booking->price_amount);

        $log = PaymentLog::query()->where('payment_id', $claim->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame('submitted', $log->old_values['status']);
        $this->assertSame('verified', $log->new_values['status']);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_a_fixed_deposit_is_not_charged_again_when_more_work_is_accepted(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, $this->setting('fixed', '100.00'));
        $booking = $this->acceptedQuotation();
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(10000, $summary['required_deposit_cents']);
        $this->assertSame(0, $summary['additional_deposit_cents']);

        $this->secondQuotation($booking, '200.00');
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(10000, $summary['required_deposit_cents']);
        $this->assertSame(0, $summary['additional_deposit_cents']);
        $this->assertSame(125000, $summary['contract_cents']);
    }

    public function test_a_later_deposit_setting_does_not_recalculate_the_original_rate(): void
    {
        $booking = $this->booking();
        $this->travel(2)->minutes();
        $this->percentage('10.00');
        $this->acceptQuotation($booking);
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['original_deposit_cents']);
        $this->assertSame(2000, $summary['additional_deposit_cents']);
        $this->assertSame(2000, $summary['required_deposit_cents']);
        $this->assertSame(105000, $summary['contract_cents']);
    }

    public function test_an_administrator_can_reject_a_receipt_and_the_customer_can_send_another(): void
    {
        $this->percentage('10.00');
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.payments.store', $booking), $this->claim([
            'payment_reference' => 'REJECT 2002',
        ]))->assertRedirect();
        $claim = Payment::query()->where('payment_reference', 'REJECT 2002')->firstOrFail();
        $proofs = $claim->proofs()->count();

        $this->actingAs($admin)->post(route('admin.payments.reject', $claim), [])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($admin)->post(route('admin.payments.reject', $claim), [
            'rejection_reason' => 'The reference does not match the receipt.',
        ])->assertRedirect();

        $claim->refresh();
        $this->assertSame('rejected', $claim->status);
        $this->assertSame('rejected', $claim->verification_status);
        $this->assertSame($admin->id, $claim->rejected_by);
        $this->assertNotNull($claim->rejected_at);
        $this->assertSame($proofs, $claim->proofs()->count());
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['verified_cents']);
        $this->assertSame(2000, $summary['rejected_cents']);
        $this->assertSame(105000, $summary['eligible_cents']);

        $this->actingAs($customer)->post(route('bookings.payments.store', $booking), $this->claim([
            'payment_reference' => 'AGAIN 2003',
        ]))->assertRedirect();
        $this->assertSame('submitted', Payment::query()->where('payment_reference', 'AGAIN 2003')->firstOrFail()->status);
        $this->assertSame('rejected', $claim->refresh()->status);

        $this->actingAs($customer)->get(route('bookings.payments.index', $booking))
            ->assertOk()
            ->assertSee('What you pay')
            ->assertSee('Booked service')
            ->assertSee('Extra work')
            ->assertSee('₱1,050.00')
            ->assertSee('Still to pay')
            ->assertSee('Payment rejected')
            ->assertSee('The reference does not match the receipt.')
            ->assertSee('d-grid', false);

        $this->actingAs($admin)->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Waiting for verification')
            ->assertSee('AGAIN 2003')
            ->assertDontSee('REJECT 2002');

        $this->actingAs($admin)->get(route('admin.payments.show', $claim))
            ->assertOk()
            ->assertSee('Original advertised rate')
            ->assertSee('Total contract amount')
            ->assertSee('The reference does not match the receipt.');
    }

    public function test_a_verified_total_cannot_make_the_outstanding_balance_negative(): void
    {
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => '5000.00',
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ])->forceFill(['verification_status' => 'verified'])->save();

        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['balance_outstanding_cents']);
        $this->assertSame(0, $summary['deposit_outstanding_cents']);
        $this->assertTrue($summary['paid_in_full']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claim(array $overrides = []): array
    {
        return array_merge([
            'payment_type' => 'deposit',
            'method' => 'gcash',
            'amount' => '20.00',
            'payment_reference' => 'GCASH 1001',
            'notes' => 'Sent from my wallet.',
            'proof' => UploadedFile::fake()->image('receipt.jpg', 12, 12),
        ], $overrides);
    }

    private function percentage(string $rate): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, $this->setting('percentage', $rate));
    }

    /**
     * @return array<string, mixed>
     */
    private function setting(string $type, string $value): array
    {
        return [
            'name' => 'Visit deposit',
            'deposit_type' => $type,
            'deposit_value' => $value,
            'minimum_deposit_amount' => null,
            'maximum_deposit_amount' => null,
            'is_active' => true,
            'effective_at' => now()->subMinute()->toDateTimeString(),
        ];
    }

    private function acceptedQuotation(): Booking
    {
        return $this->acceptQuotation($this->booking());
    }

    private function acceptQuotation(Booking $booking): Booking
    {
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), [
            'items' => [[
                'item_type' => 'labor',
                'description' => 'Replace kitchen trap',
                'quantity' => '2',
                'unit' => 'hour',
                'unit_price' => '100.00',
            ]],
            'notes' => 'The trap is under the sink.',
        ])->assertRedirect();
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $quotation = $booking->refresh()->currentQuotation()->firstOrFail();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => $quotation->version_number,
        ])->assertRedirect();

        return $booking->refresh();
    }

    private function secondQuotation(Booking $booking, string $amount): void
    {
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $whole = (int) $amount;

        $this->actingAs($worker)->post(route('jobs.quotation.store', $booking), [
            'title' => 'Extra pipe',
            'items' => [[
                'item_type' => 'material',
                'description' => 'Extra pipe',
                'quantity' => '1',
                'unit' => 'piece',
                'unit_price' => $amount,
            ]],
            'notes' => 'Another length of pipe.',
        ])->assertRedirect();
        $requestId = (int) $booking->quotations()->where('status', 'draft')->value('quotation_request_id');
        $this->actingAs($worker)->post(route('jobs.quotation.submit', $booking), [
            'quotation_request_id' => $requestId,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking), [
            'quotation_request_id' => $requestId,
        ])->assertRedirect();
        $quotation = $booking->refresh()->quotationRequests()->whereKey($requestId)->firstOrFail()->currentQuotation;
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $requestId,
            'version_number' => $quotation->version_number,
        ])->assertRedirect();
        $this->assertSame($whole * 100, \App\Support\Money::toCents((string) $quotation->refresh()->total_amount));
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
}
