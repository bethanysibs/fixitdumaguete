<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\PaymentProof;
use App\Models\PaymentSetting;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\CommissionCalculator;
use App\Services\DepositCalculator;
use App\Services\PaymentCalculator;
use App\Services\PaymentRecorder;
use App\Services\PaymentSettingService;
use App\Support\Money;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_a_booking_can_hold_more_than_one_payment_without_mass_assigning_verification(): void
    {
        $booking = $this->booking();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        foreach (['10.00', '20.00'] as $amount) {
            Payment::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'status' => 'paid',
                'method' => 'platform',
                'paid_at' => now(),
                'quotation_id' => 999,
                'quotation_version' => 4,
                'payment_type' => 'deposit',
                'verification_status' => 'verified',
            ]);
        }

        $payments = Payment::query()->where('booking_id', $booking->id)->get();
        $this->assertCount(2, $payments);
        $this->assertTrue($payments->every(fn (Payment $payment) => $payment->quotation_id === null
            && $payment->quotation_version === null
            && $payment->payment_type === null
            && $payment->verification_status === 'not_submitted'));

        $this->assertTrue(Schema::hasColumns('payments', [
            'quotation_id',
            'quotation_version',
            'payment_type',
            'verification_status',
            'payment_reference',
            'submitted_by',
            'verified_by',
            'verified_at',
            'rejected_by',
            'rejected_at',
            'rejection_reason',
            'notes',
            'currency',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_settings', [
            'name',
            'deposit_type',
            'deposit_value',
            'minimum_deposit_amount',
            'maximum_deposit_amount',
            'is_active',
            'effective_at',
            'created_by',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_proofs', [
            'payment_id',
            'file_path',
            'original_filename',
            'mime_type',
            'file_size',
            'uploaded_by',
            'uploaded_at',
        ]));
        $this->assertFalse(Schema::hasColumn('payment_proofs', 'url'));
        $this->assertTrue(Schema::hasColumns('payment_logs', [
            'payment_id',
            'user_id',
            'action',
            'old_values',
            'new_values',
            'description',
            'created_at',
        ]));

        $bookingIsUnique = collect(Schema::getIndexes('payments'))
            ->contains(fn (array $index) => ($index['columns'] ?? []) === ['booking_id'] && ($index['unique'] ?? false));
        $this->assertFalse($bookingIsUnique);
    }

    public function test_payment_settings_keep_each_version_and_reject_invalid_ones(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $service = app(PaymentSettingService::class);

        $first = $service->record($admin, $this->setting('percentage', '10.00'));
        $second = $service->record($admin, $this->setting('fixed', '75.00', [
            'effective_at' => now()->addDay()->toDateTimeString(),
        ]));

        $first->refresh();
        $this->assertSame('10.00', $first->deposit_value);
        $this->assertTrue($first->is_active);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, $service->applicableAt(now())?->id);
        $this->assertSame($second->id, $service->applicableAt(now()->addDays(2))?->id);

        foreach ([
            $this->setting('percentage', '150.00'),
            $this->setting('fixed', '-1.00'),
            $this->setting('tiered', '10.00'),
            $this->setting('percentage', '10.00', ['name' => '']),
            $this->setting('percentage', '10.00', [
                'minimum_deposit_amount' => '80.00',
                'maximum_deposit_amount' => '10.00',
            ]),
            $this->setting('percentage', '10.00', ['is_active' => 'yes']),
            $this->setting('percentage', '10.00', ['effective_at' => 'not-a-date']),
        ] as $input) {
            $this->assertRejected(fn () => $service->record($admin, $input), ValidationException::class);
        }

        $this->assertRejected(fn () => $service->record($customer, $this->setting('fixed', '20.00')), AuthorizationException::class);
        $this->assertRejected(fn () => $service->record($worker, $this->setting('fixed', '20.00')), AuthorizationException::class);
        $this->assertSame(2, PaymentSetting::query()->count());
        $this->assertSame('10.00', $first->refresh()->deposit_value);
    }

    public function test_the_deposit_uses_the_accepted_quotation_and_its_setting_version(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $service = app(PaymentSettingService::class);
        $calculator = app(DepositCalculator::class);
        $payments = Payment::query()->count();
        $submitted = $this->submitQuotation();
        $this->assertRejected(
            fn () => $calculator->calculate($submitted->currentQuotation()->firstOrFail()),
            ValidationException::class,
        );

        $unconfigured = $this->acceptedQuotation();
        $open = $calculator->calculate($unconfigured->currentQuotation()->firstOrFail());
        $this->assertSame('unconfigured', $open['requirement']);
        $this->assertSame(20000, $open['accepted_amount_cents']);
        $this->assertSame(0, $open['final_deposit_cents']);
        $this->assertSame(20000, $open['remaining_balance_cents']);
        $this->assertNotSame(Money::toCents((string) $unconfigured->price_amount), $open['accepted_amount_cents']);

        $this->travelTo(now()->addMinute());
        $percentage = $service->record($admin, $this->setting('percentage', '10.00'));
        $percentageBooking = $this->acceptedQuotation();
        $percentageQuote = $percentageBooking->currentQuotation()->firstOrFail();
        $tenPercent = $calculator->calculate($percentageQuote);
        $this->assertSame('percentage', $tenPercent['requirement']);
        $this->assertSame($percentage->id, $tenPercent['setting_id']);
        $this->assertSame('10.00', $tenPercent['deposit_value']);
        $this->assertSame(2000, $tenPercent['raw_deposit_cents']);
        $this->assertSame(0, $tenPercent['minimum_adjustment_cents']);
        $this->assertSame(0, $tenPercent['maximum_adjustment_cents']);
        $this->assertSame(2000, $tenPercent['final_deposit_cents']);
        $this->assertSame(18000, $tenPercent['remaining_balance_cents']);

        $this->travelTo(now()->addMinute());
        $service->record($admin, $this->setting('fixed', '75.00'));
        $fixed = $calculator->calculate($this->acceptedQuotation()->currentQuotation()->firstOrFail());
        $this->assertSame('fixed', $fixed['requirement']);
        $this->assertSame(7500, $fixed['raw_deposit_cents']);
        $this->assertSame(7500, $fixed['final_deposit_cents']);
        $this->assertSame(12500, $fixed['remaining_balance_cents']);

        $this->travelTo(now()->addMinute());
        $service->record($admin, $this->setting('percentage', '10.00', [
            'minimum_deposit_amount' => '50.00',
        ]));
        $minimum = $calculator->calculate($this->acceptedQuotation()->currentQuotation()->firstOrFail());
        $this->assertSame(2000, $minimum['raw_deposit_cents']);
        $this->assertSame(3000, $minimum['minimum_adjustment_cents']);
        $this->assertSame(5000, $minimum['final_deposit_cents']);
        $this->assertSame(15000, $minimum['remaining_balance_cents']);

        $this->travelTo(now()->addMinute());
        $service->record($admin, $this->setting('percentage', '50.00', [
            'maximum_deposit_amount' => '40.00',
        ]));
        $maximum = $calculator->calculate($this->acceptedQuotation()->currentQuotation()->firstOrFail());
        $this->assertSame(10000, $maximum['raw_deposit_cents']);
        $this->assertSame(-6000, $maximum['maximum_adjustment_cents']);
        $this->assertSame(4000, $maximum['final_deposit_cents']);
        $this->assertSame(16000, $maximum['remaining_balance_cents']);

        $this->travelTo(now()->addMinute());
        $service->record($admin, $this->setting('fixed', '250.00'));
        $capped = $calculator->calculate($this->acceptedQuotation()->currentQuotation()->firstOrFail());
        $this->assertSame(25000, $capped['raw_deposit_cents']);
        $this->assertSame(-5000, $capped['quotation_cap_adjustment_cents']);
        $this->assertSame(20000, $capped['final_deposit_cents']);
        $this->assertSame(0, $capped['remaining_balance_cents']);

        $this->travelTo(now()->addMinute());
        $service->record($admin, $this->setting('percentage', '50.00', ['is_active' => false]));
        $disabled = $calculator->calculate($this->acceptedQuotation()->currentQuotation()->firstOrFail());
        $this->assertSame('disabled', $disabled['requirement']);
        $this->assertSame('50.00', $disabled['deposit_value']);
        $this->assertSame(0, $disabled['final_deposit_cents']);
        $this->assertSame(20000, $disabled['remaining_balance_cents']);

        $original = $calculator->calculate($percentageQuote->refresh());
        $this->assertSame($percentage->id, $original['setting_id']);
        $this->assertSame(2000, $original['final_deposit_cents']);
        $this->assertSame('850.00', $percentageBooking->refresh()->price_amount);
        $this->assertSame($payments, Payment::query()->count());
    }

    public function test_only_verified_payments_count_toward_the_deposit_and_balance(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, $this->setting('percentage', '50.00'));
        $booking = $this->acceptedQuotation();
        $financial = $this->financial($booking);
        $quotation = $booking->currentQuotation()->firstOrFail();
        $customer = User::query()->findOrFail($booking->customer_id);

        $counted = [
            $this->payment($booking, $quotation, $customer, '30.00', 'milestone'),
            $this->payment($booking, $quotation, $customer, '20.00', 'balance'),
        ];
        $this->mark($counted[0], 'verified', 'verified');
        $this->mark($counted[1], 'paid', 'verified');

        $pending = $this->payment($booking, $quotation, $customer, '40.00', 'deposit');
        $this->mark($this->payment($booking, $quotation, $customer, '15.00', 'adjustment'), 'rejected', 'rejected');
        $this->mark($this->payment($booking, $quotation, $customer, '10.00', 'full_payment'), 'cancelled', 'rejected');
        $submitted = $this->payment($booking, $quotation, $customer, '12.00', 'deposit');
        $submitted->forceFill([
            'status' => 'submitted',
            'verification_status' => 'pending_review',
            'payment_reference' => 'GCASH-123',
        ])->save();
        PaymentProof::query()->forceCreate([
            'payment_id' => $submitted->id,
            'file_path' => 'payment-proofs/'.$submitted->id.'/receipt.jpg',
            'original_filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'uploaded_by' => $customer->id,
            'uploaded_at' => now(),
        ]);
        $this->mark($this->payment($booking, $quotation, $customer, '8.00', 'balance'), 'verified', 'pending_review');
        $this->mark($this->payment($booking, $quotation, $customer, '7.00', 'deposit'), 'refunded', 'verified');

        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(5000, $summary['verified_cents']);
        $this->assertSame(10000, $summary['required_deposit_cents']);
        $this->assertSame(5000, $summary['deposit_outstanding_cents']);
        $this->assertSame(15000, $summary['balance_outstanding_cents']);
        $this->assertFalse($summary['requirements_met']);
        $this->assertFalse($summary['paid_in_full']);
        $this->assertSame('pending', $pending->refresh()->status);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => $booking->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ])->forceFill(['verification_status' => 'verified'])->save();

        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(5000, $summary['verified_cents']);
        $this->assertSame(20000, $summary['accepted_amount_cents']);

        $this->mark($this->payment($booking, $quotation, $customer, '50.00', 'balance'), 'verified', 'verified');
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(10000, $summary['verified_cents']);
        $this->assertSame(0, $summary['deposit_outstanding_cents']);
        $this->assertSame(10000, $summary['balance_outstanding_cents']);
        $this->assertTrue($summary['requirements_met']);
        $this->assertFalse($summary['paid_in_full']);
        $this->assertSame('100.00', $booking->verifiedPaymentTotal());
        $this->assertSame('100.00', $booking->outstandingBalance());
        $this->assertSame($financial, $this->financial($booking->refresh()));
        $this->assertSame('200.00', $quotation->refresh()->total_amount);
        $this->assertSame('pending', $booking->status);
    }

    public function test_a_payment_stays_on_the_accepted_version_and_does_not_change_the_booking(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, $this->setting('percentage', '10.00'));
        $recorder = app(PaymentRecorder::class);

        $submitted = $this->submitQuotation();
        $this->assertRejected(
            fn () => $recorder->record($submitted, $submitted->currentQuotation()->firstOrFail()->id, 1, '20.00', 'deposit', 'gcash', $customer),
            ValidationException::class,
        );
        $this->actingAs($admin)->post(route('admin.quotations.approve', $submitted))->assertRedirect();
        $submitted->refresh();
        $this->assertRejected(
            fn () => $recorder->record($submitted, $submitted->currentQuotation()->firstOrFail()->id, 1, '20.00', 'deposit', 'gcash', $customer),
            ValidationException::class,
        );
        $this->assertSame(0, Payment::query()->where('booking_id', $submitted->id)->count());

        $booking = $this->acceptedQuotation();
        $quotation = $booking->currentQuotation()->firstOrFail();
        $financial = $this->financial($booking);
        $other = $this->booking();
        $otherCustomer = User::query()->create([
            'name' => 'Other Customer',
            'email' => 'other-customer@fixitdumaguete.test',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->assertRejected(
            fn () => $recorder->record($booking, $quotation->id, 9, '20.00', 'deposit', 'gcash', $customer),
            ValidationException::class,
        );
        $this->assertRejected(
            fn () => $recorder->record($other, $quotation->id, $quotation->version_number, '20.00', 'deposit', 'gcash', $customer),
            ValidationException::class,
        );
        $this->assertRejected(
            fn () => $recorder->record($booking, $quotation->id, $quotation->version_number, '20.00', 'deposit', 'gcash', $worker),
            AuthorizationException::class,
        );
        $this->assertRejected(
            fn () => $recorder->record($booking, $quotation->id, $quotation->version_number, '20.00', 'deposit', 'gcash', $admin),
            AuthorizationException::class,
        );
        $this->assertRejected(
            fn () => $recorder->record($booking, $quotation->id, $quotation->version_number, '20.00', 'deposit', 'gcash', $otherCustomer),
            AuthorizationException::class,
        );

        $payment = $recorder->record($booking, $quotation->id, $quotation->version_number, '20.00', 'deposit', 'gcash', $customer);
        $this->assertSame($quotation->id, $payment->quotation_id);
        $this->assertSame(1, $payment->quotation_version);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('not_submitted', $payment->verification_status);
        $this->assertSame('20.00', $payment->amount);
        $this->assertSame($customer->id, $payment->submitted_by);
        $this->assertSame($financial, $this->financial($booking->refresh()));
        $this->assertSame('pending', $booking->status);
        $this->assertSame('200.00', $quotation->refresh()->total_amount);
        $this->assertSame('approved', $quotation->status);
        $this->assertFalse(app(PaymentCalculator::class)->summarize($booking)['requirements_met']);

        $this->mark($payment, 'verified', 'verified');
        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame(2000, $summary['verified_cents']);
        $this->assertTrue($summary['requirements_met']);
        $this->assertSame(18000, $summary['balance_outstanding_cents']);
        $this->assertSame($financial, $this->financial($booking->refresh()));

        $log = PaymentLog::query()->where('payment_id', $payment->id)->where('action', 'recorded')->firstOrFail();
        $this->assertSame('pending', $log->new_values['status']);
        $this->assertNull($log->old_values);
        $update = PaymentLog::query()->where('payment_id', $payment->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame('pending', $update->old_values['status']);
        $this->assertSame('verified', $update->new_values['status']);

        $booking->update(['status' => 'completed']);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => $booking->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $payment->forceFill(['status' => 'refunded'])->save();
        $this->assertTrue(app(CommissionCalculator::class)->hasCompletedPaidBooking($customer->id, $booking->worker_profile_id));
        $repeat = $this->booking();
        $this->assertSame('repeat', $repeat->commission_rule);
        $this->assertSame($financial, $this->financial($booking->refresh()));
    }

    public function test_a_revised_quotation_needs_a_new_payment_decision(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        app(PaymentSettingService::class)->record($admin, $this->setting('percentage', '10.00'));
        $booking = $this->acceptedQuotation();
        $financial = $this->financial($booking);
        $accepted = $booking->currentQuotation()->firstOrFail();
        $acceptedAt = $accepted->customer_approved_at->toDateTimeString();
        $payment = $this->mark(
            $this->payment($booking, $accepted, $customer, '20.00', 'deposit'),
            'verified',
            'verified',
        );

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

        $accepted->refresh();
        $revised = $booking->currentQuotation()->firstOrFail();
        $this->assertSame('approved', $accepted->status);
        $this->assertFalse($accepted->is_current);
        $this->assertSame('200.00', $accepted->total_amount);
        $this->assertSame($acceptedAt, $accepted->customer_approved_at->toDateTimeString());
        $this->assertSame('submitted', $revised->status);
        $this->assertNull($revised->customer_approved_at);
        $this->assertSame('80.00', $revised->total_amount);
        $this->assertNull(app(PaymentCalculator::class)->summarize($booking->refresh())['quotation_id']);
        $this->assertRejected(
            fn () => app(PaymentRecorder::class)->record($booking, $revised->id, 2, '8.00', 'deposit', 'gcash', $customer),
            ValidationException::class,
        );

        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $revised->refresh();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => $revised->version_number,
        ])->assertRedirect();

        $summary = app(PaymentCalculator::class)->summarize($booking->refresh());
        $this->assertSame($revised->id, $summary['quotation_id']);
        $this->assertSame(2, $summary['quotation_version']);
        $this->assertSame(8000, $summary['accepted_amount_cents']);
        $this->assertSame(0, $summary['verified_cents']);
        $this->assertSame(800, $summary['required_deposit_cents']);
        $this->assertFalse($summary['requirements_met']);
        $this->assertSame($accepted->id, $payment->refresh()->quotation_id);
        $this->assertSame(1, $payment->quotation_version);
        $this->assertSame('verified', $payment->status);
        $this->assertSame('20.00', $payment->amount);
        $this->assertSame($financial, $this->financial($booking->refresh()));
        $this->assertSame('pending', $booking->status);
    }

    public function test_payment_proof_stays_private(): void
    {
        $this->assertStringEndsWith('app/private', config('filesystems.disks.local.root'));
        $this->assertSame('public', config('filesystems.disks.public.visibility'));
        $this->assertFalse(collect(Route::getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'payment-proof') || str_contains($route->uri(), 'payment_proof'),
        ));

        $booking = $this->booking();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $other = User::query()->create([
            'name' => 'Other Customer',
            'email' => 'proof-customer@fixitdumaguete.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => '10.00',
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $proof = PaymentProof::query()->forceCreate([
            'payment_id' => $payment->id,
            'file_path' => 'payment-proofs/'.$payment->id.'/receipt.jpg',
            'original_filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1200,
            'uploaded_by' => $customer->id,
            'uploaded_at' => now(),
        ]);

        $this->assertSame('local', $proof->disk());
        $this->assertFalse($proof->canBeViewedBy(null));
        $this->assertFalse($proof->canBeViewedBy($worker));
        $this->assertFalse($proof->canBeViewedBy($other));
        $this->assertTrue($proof->canBeViewedBy($customer));
        $this->assertTrue($proof->canBeViewedBy($admin));

        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put($proof->file_path, 'private receipt');
        $this->assertTrue(Storage::disk('local')->exists($proof->file_path));
        $this->assertFalse(Storage::disk('public')->exists($proof->file_path));
        auth()->logout();
        $response = $this->get('/storage/'.$proof->file_path);
        $this->assertContains($response->status(), [403, 404]);
        $this->assertStringNotContainsString('private receipt', $response->getContent());

        $this->assertRejected(fn () => PaymentProof::query()->forceCreate([
            'payment_id' => $payment->id,
            'file_path' => 'https://example.test/receipt.jpg',
            'original_filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1200,
            'uploaded_by' => $customer->id,
            'uploaded_at' => now(),
        ]), ValidationException::class);
        $this->assertSame(1, PaymentProof::query()->count());
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function setting(string $type, string $value, array $extra = []): array
    {
        return array_merge([
            'name' => 'Visit deposit',
            'deposit_type' => $type,
            'deposit_value' => $value,
            'minimum_deposit_amount' => null,
            'maximum_deposit_amount' => null,
            'is_active' => true,
            'effective_at' => now()->toDateTimeString(),
        ], $extra);
    }

    private function payment(Booking $booking, Quotation $quotation, User $customer, string $amount, string $type): Payment
    {
        return app(PaymentRecorder::class)->record(
            $booking,
            $quotation->id,
            $quotation->version_number,
            $amount,
            $type,
            'gcash',
            $customer,
        );
    }

    private function mark(Payment $payment, string $status, string $verification): Payment
    {
        $payment->forceFill([
            'status' => $status,
            'verification_status' => $verification,
        ])->save();

        return $payment->refresh();
    }

    private function assertRejected(callable $callback, string $exception): void
    {
        try {
            $callback();
            $this->fail('The payment action was accepted.');
        } catch (ValidationException|AuthorizationException $caught) {
            $this->assertInstanceOf($exception, $caught);
        }
    }

    private function acceptedQuotation(): Booking
    {
        $booking = $this->submitQuotation();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.quotations.approve', $booking))->assertRedirect();
        $quotation = $booking->refresh()->currentQuotation()->firstOrFail();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => $quotation->version_number,
        ])->assertRedirect();

        return $booking->refresh();
    }

    private function submitQuotation(): Booking
    {
        $booking = $this->booking();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

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
