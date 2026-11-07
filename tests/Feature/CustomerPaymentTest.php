<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentInstruction;
use App\Models\PaymentProof;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\PaymentCalculator;
use App\Services\PaymentSettingService;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_only_the_booking_customer_can_open_the_payment_page(): void
    {
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $other = User::query()->create([
            'name' => 'Other Customer',
            'email' => 'other-customer@fixitdumaguete.test',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->actingAs($customer)
            ->get(route('bookings.payments.index', $booking))
            ->assertOk()
            ->assertSee('Version 1')
            ->assertSee('₱200.00')
            ->assertSee('₱20.00')
            ->assertSee('Account details have not been added yet.')
            ->assertSee('Submit a payment claim')
            ->assertDontSee('Payment recorded.');

        $this->actingAs($customer)
            ->get(route('bookings.payments.create', $booking))
            ->assertOk()
            ->assertSee('Submit claim for review');

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Submit a payment');

        $this->actingAs($other)->get(route('bookings.payments.index', $booking))->assertForbidden();
        $this->actingAs($other)->get(route('bookings.payments.create', $booking))->assertForbidden();
        $this->actingAs($worker)->get(route('bookings.payments.index', $booking))->assertForbidden();
        $this->actingAs($worker)->post(route('bookings.payments.store', $booking), $this->claim())->assertForbidden();
        $this->actingAs($admin)->get(route('bookings.payments.create', $booking))->assertForbidden();
        $this->actingAs($admin)->post(route('bookings.payments.store', $booking), $this->claim())->assertForbidden();

        auth()->logout();
        $this->get(route('bookings.payments.index', $booking))->assertRedirect(route('login'));
    }

    public function test_a_customer_can_submit_a_claim_without_changing_the_booking(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $quotation = $booking->currentQuotation()->firstOrFail();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_type' => 'deposit',
                'amount' => '20.00',
                'payment_reference' => 'GCASH 1001',
                'notes' => 'Sent from my own wallet.',
                'quotation_id' => 999,
                'total_amount' => '1.00',
                'status' => 'verified',
                'verification_status' => 'verified',
                'proof' => UploadedFile::fake()->image('receipt.jpg', 12, 12),
            ]))
            ->assertRedirect(route('bookings.payments.index', $booking))
            ->assertSessionHas('success');

        $payment = Payment::query()->where('booking_id', $booking->id)->whereNotNull('quotation_id')->firstOrFail();
        $this->assertSame($quotation->id, $payment->quotation_id);
        $this->assertSame(1, $payment->quotation_version);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame($customer->id, $payment->submitted_by);
        $this->assertSame('deposit', $payment->payment_type);
        $this->assertSame('gcash', $payment->method);
        $this->assertSame('20.00', $payment->amount);
        $this->assertSame('GCASH 1001', $payment->payment_reference);
        $this->assertSame('Sent from my own wallet.', $payment->notes);
        $this->assertSame('submitted', $payment->status);
        $this->assertSame('pending_review', $payment->verification_status);
        $this->assertNotNull($payment->submitted_at);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->verified_at);
        $this->assertNull($payment->paid_at);
        $this->assertSame(0, app(PaymentCalculator::class)->summarize($booking->refresh())['verified_cents']);
        $this->assertSame($financial, $this->financial($booking));
        $this->assertSame('pending', $booking->status);
        $this->assertSame('200.00', $quotation->refresh()->total_amount);
        $this->assertFalse(Schema::hasTable('payouts'));

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_type' => 'balance',
                'amount' => '180.00',
                'payment_reference' => 'BANK 2002',
                'method' => 'bank_transfer',
                'proof' => UploadedFile::fake()->image('balance.jpg', 12, 12),
            ]))
            ->assertRedirect();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_type' => 'balance',
                'amount' => '1.00',
                'payment_reference' => 'BANK 2003',
            ]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_type' => 'balance',
                'amount' => '180.00',
                'payment_reference' => 'BANK 2002',
                'method' => 'bank_transfer',
            ]))
            ->assertSessionHasErrors('payment_reference');

        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(0, $summary['verified_cents']);
        $this->assertSame(2000, $summary['deposit_outstanding_cents']);
        $this->assertSame(20000, $summary['balance_outstanding_cents']);
        $this->assertSame($financial, $this->financial($booking));

        $full = $this->acceptedQuotation();
        $fullFinancial = $this->financial($full);
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $full), $this->claim([
                'payment_type' => 'full_payment',
                'amount' => '200.00',
                'payment_reference' => 'FULL 3003',
            ]))
            ->assertRedirect();
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $full), $this->claim([
                'payment_type' => 'full_payment',
                'amount' => '50.00',
                'payment_reference' => 'FULL 3004',
            ]))
            ->assertSessionHasErrors('amount');

        $fullPayment = Payment::query()->where('booking_id', $full->id)->whereNotNull('quotation_id')->firstOrFail();
        $this->assertSame('full_payment', $fullPayment->payment_type);
        $this->assertSame('submitted', $fullPayment->status);
        $this->assertSame('pending_review', $fullPayment->verification_status);
        $this->assertSame($fullFinancial, $this->financial($full->refresh()));
        $this->assertSame('pending', $full->status);
    }

    public function test_invalid_amounts_methods_and_files_are_rejected(): void
    {
        Storage::fake('local');
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['amount' => '0.00']))
            ->assertSessionHasErrors('amount');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['amount' => '21.00']))
            ->assertSessionHasErrors('amount');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_type' => 'balance',
                'amount' => '250.00',
                'payment_reference' => 'OVER 1002',
            ]))
            ->assertSessionHasErrors('amount');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['method' => 'cash', 'payment_reference' => 'CASH 1003']))
            ->assertSessionHasErrors('method');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['payment_reference' => '']))
            ->assertSessionHasErrors('payment_reference');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['proof' => null]))
            ->assertSessionHasErrors('proof');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'proof' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                'payment_reference' => 'TEXT 1004',
            ]))
            ->assertSessionHasErrors('proof');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'proof' => UploadedFile::fake()->create('receipt.jpg', 5121, 'image/jpeg'),
                'payment_reference' => 'BIG 1005',
            ]))
            ->assertSessionHasErrors('proof');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'proof' => UploadedFile::fake()->image('receipt.php.jpg', 12, 12),
                'payment_reference' => 'PHP 1006',
            ]))
            ->assertSessionHasErrors('proof');

        PaymentInstruction::query()->create([
            'method' => 'gcash',
            'account_name' => 'Hidden Wallet',
            'account_number' => 'SECRET-9911',
            'instructions' => 'Do not show this inactive wallet.',
            'is_active' => false,
        ]);

        $this->actingAs($customer)
            ->get(route('bookings.payments.index', $booking))
            ->assertOk()
            ->assertDontSee('SECRET-9911')
            ->assertSee('Payment methods are turned off');
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim(['payment_reference' => 'HIDE 1007']))
            ->assertSessionHasErrors('method');

        $this->assertSame(0, Payment::query()->where('booking_id', $booking->id)->whereNotNull('quotation_id')->count());
    }

    public function test_payment_proof_stays_private(): void
    {
        $this->assertStringEndsWith('app/private', config('filesystems.disks.local.root'));
        Storage::fake('local');
        Storage::fake('public');
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $other = User::query()->create([
            'name' => 'Other Customer',
            'email' => 'proof-customer@fixitdumaguete.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $source = UploadedFile::fake()->image('receipt.jpg', 12, 12);
        $upload = new UploadedFile($source->getPathname(), '../../etc/passwd.jpg', 'image/jpeg', null, true);

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'proof' => $upload,
                'payment_reference' => 'PROOF 4004',
            ]))
            ->assertRedirect();

        $payment = Payment::query()->where('booking_id', $booking->id)->whereNotNull('quotation_id')->firstOrFail();
        $proof = PaymentProof::query()->where('payment_id', $payment->id)->firstOrFail();
        $this->assertSame('local', $proof->disk());
        $this->assertMatchesRegularExpression('#^payment-proofs/\d+/[0-9a-f-]{36}\.jpg$#', $proof->file_path);
        $this->assertStringNotContainsString('..', $proof->file_path);
        $this->assertStringNotContainsString('passwd', $proof->file_path);
        $this->assertTrue(Storage::disk('local')->exists($proof->file_path));
        $this->assertFalse(Storage::disk('public')->exists($proof->file_path));

        $download = $this->actingAs($customer)->get(route('bookings.payments.proof', [$booking, $payment, $proof]));
        $download->assertOk();
        $this->assertStringNotContainsString('..', (string) $download->headers->get('content-disposition'));

        $this->actingAs($other)->get(route('bookings.payments.proof', [$booking, $payment, $proof]))->assertForbidden();
        $this->actingAs($worker)->get(route('bookings.payments.proof', [$booking, $payment, $proof]))->assertForbidden();
        $this->actingAs($admin)->get(route('bookings.payments.proof', [$booking, $payment, $proof]))->assertOk();

        auth()->logout();
        $this->get(route('bookings.payments.proof', [$booking, $payment, $proof]))->assertRedirect(route('login'));
        $public = $this->get('/storage/'.$proof->file_path);
        $this->assertContains($public->status(), [403, 404]);
    }

    public function test_a_rejected_claim_stays_visible_and_can_be_replaced(): void
    {
        Storage::fake('local');
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_reference' => 'REJECT 5005',
            ]))
            ->assertRedirect();

        $rejected = Payment::query()->where('payment_reference', 'REJECT 5005')->firstOrFail();
        $rejected->forceFill([
            'status' => 'rejected',
            'verification_status' => 'rejected',
            'rejection_reason' => 'The receipt is unreadable.',
            'rejected_at' => now(),
        ])->save();

        $this->actingAs($customer)
            ->get(route('bookings.payments.index', $booking))
            ->assertOk()
            ->assertSee('The receipt is unreadable.')
            ->assertSee('Rejected')
            ->assertSee('REJECT 5005')
            ->assertDontSee('Payment recorded.');

        $this->assertSame(0, app(PaymentCalculator::class)->summarize($booking->refresh())['verified_cents']);

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_reference' => 'RETRY 5006',
            ]))
            ->assertRedirect();

        $rejected->refresh();
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('20.00', $rejected->amount);
        $this->assertSame('The receipt is unreadable.', $rejected->rejection_reason);
        $this->assertSame('submitted', Payment::query()->where('payment_reference', 'RETRY 5006')->firstOrFail()->status);
        $this->assertSame(0, app(PaymentCalculator::class)->summarize($booking->refresh())['verified_cents']);
    }

    public function test_a_revised_quotation_does_not_inherit_a_payment_claim(): void
    {
        Storage::fake('local');
        $this->recordDeposit();
        $booking = $this->acceptedQuotation();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $financial = $this->financial($booking);
        $accepted = $booking->currentQuotation()->firstOrFail();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'payment_reference' => 'OLD 6006',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)->put(route('admin.quotations.update', $booking), [
            'items' => [[
                'item_type' => 'labor',
                'description' => 'Shorter repair',
                'quantity' => '2',
                'unit' => 'hour',
                'unit_price' => '40.00',
            ]],
            'notes' => 'A shorter repair.',
            'admin_notes' => 'Reduced the hours.',
            'total_amount' => '1.00',
        ])->assertRedirect();

        $revised = $booking->refresh()->currentQuotation()->firstOrFail();
        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'amount' => '8.00',
                'payment_reference' => 'NEW 6007',
                'quotation_id' => $accepted->id,
                'status' => 'paid',
            ]))
            ->assertSessionHasErrors('quotation');

        $accepted->refresh();
        $old = Payment::query()->where('payment_reference', 'OLD 6006')->firstOrFail();
        $this->assertSame('approved', $accepted->status);
        $this->assertSame('200.00', $accepted->total_amount);
        $this->assertSame($accepted->id, $old->quotation_id);
        $this->assertSame(1, $old->quotation_version);
        $this->assertSame('20.00', $old->amount);
        $this->assertSame('submitted', $old->status);
        $this->assertNull(Payment::query()->where('payment_reference', 'NEW 6007')->first());

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $revised->refresh();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => $revised->version_number,
            'total_amount' => '1.00',
        ])->assertRedirect();

        $this->actingAs($customer)
            ->post(route('bookings.payments.store', $booking), $this->claim([
                'amount' => '8.00',
                'payment_reference' => 'NEW 6007',
                'quotation_id' => $accepted->id,
                'total_amount' => '1.00',
                'status' => 'verified',
            ]))
            ->assertRedirect();

        $fresh = Payment::query()->where('payment_reference', 'NEW 6007')->firstOrFail();
        $this->assertSame($revised->id, $fresh->quotation_id);
        $this->assertSame(2, $fresh->quotation_version);
        $this->assertSame('8.00', $fresh->amount);
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('pending_review', $fresh->verification_status);
        $this->assertSame($accepted->id, $old->refresh()->quotation_id);
        $this->assertSame('20.00', $old->amount);
        $this->assertSame($financial, $this->financial($booking->refresh()));
        $this->assertSame('pending', $booking->status);
        $this->assertSame(0, app(PaymentCalculator::class)->summarize($booking)['verified_cents']);
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
            'notes' => null,
            'proof' => UploadedFile::fake()->image('receipt.jpg', 12, 12),
        ], $overrides);
    }

    private function recordDeposit(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, [
            'name' => 'Visit deposit',
            'deposit_type' => 'percentage',
            'deposit_value' => '10.00',
            'minimum_deposit_amount' => null,
            'maximum_deposit_amount' => null,
            'is_active' => true,
            'effective_at' => now()->subMinute()->toDateTimeString(),
        ]);
    }

    private function acceptedQuotation(): Booking
    {
        $booking = $this->booking();
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
