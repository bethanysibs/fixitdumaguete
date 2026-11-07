<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\ContractAmountCalculator;
use App\Services\QuotationRequestGrouper;
use App\Support\Money;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractQuotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_accepted_additional_quotations_accumulate_on_the_original_rate(): void
    {
        $booking = $this->booking();
        $financial = $this->financial($booking);
        $payments = Payment::query()->where('booking_id', $booking->id)->count();
        $customer = $this->customer();
        $worker = $this->worker();
        $admin = $this->admin();

        $first = $this->openRequest($booking, 'Extra trap', '160.00', '2', '80.00');
        $this->submit($booking, $first);
        $this->approve($booking, $first);
        $this->accept($booking, $first, 1);

        $second = $this->openRequest($booking, 'Extra pipe', '350.00', '1', '350.00');
        $firstQuote = $first->currentQuotation()->firstOrFail();
        $this->assertSame('approved', $firstQuote->status);
        $this->assertTrue($firstQuote->is_current);
        $this->assertSame('160.00', $firstQuote->total_amount);

        $this->submit($booking, $second);
        $this->approve($booking, $second);
        $this->accept($booking, $second, 1);

        $firstQuote->refresh();
        $secondQuote = $second->currentQuotation()->firstOrFail();
        $this->assertSame('approved', $firstQuote->status);
        $this->assertSame('160.00', $firstQuote->total_amount);
        $this->assertTrue($firstQuote->is_current);
        $this->assertSame('approved', $secondQuote->status);
        $this->assertSame('350.00', $secondQuote->total_amount);
        $this->assertNotSame($first->id, $second->id);

        $draft = $this->openRequest($booking, 'Unsent draft', '999.00', '1', '999.00');
        $submitted = $this->openRequest($booking, 'Waiting for admin', '111.00', '1', '111.00');
        $this->submit($booking, $submitted);
        $approvedOnly = $this->openRequest($booking, 'Waiting for customer', '222.00', '1', '222.00');
        $this->submit($booking, $approvedOnly);
        $this->approve($booking, $approvedOnly);
        $rejected = $this->openRequest($booking, 'Admin rejected', '70.00', '1', '70.00');
        $this->submit($booking, $rejected);
        $this->actingAs($admin)->post(route('admin.quotations.reject', $booking), [
            'quotation_request_id' => $rejected->id,
            'rejection_reason' => 'The scope is unclear.',
        ])->assertRedirect();
        $declined = $this->openRequest($booking, 'Customer declined', '333.00', '1', '333.00');
        $this->submit($booking, $declined);
        $this->approve($booking, $declined);
        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'quotation_request_id' => $declined->id,
            'version_number' => 1,
            'customer_rejection_reason' => 'Not needed.',
        ])->assertRedirect();

        $contract = app(ContractAmountCalculator::class)->summarize($booking->fresh());
        $this->assertSame(85000, $contract['original_rate_cents']);
        $this->assertSame(16000 + 35000, $contract['accepted_additional_cents']);
        $this->assertSame(85000 + 16000 + 35000, $contract['contract_cents']);
        $this->assertSame('1360.00', $contract['contract_amount']);
        $this->assertSame(0, $contract['verified_cents']);
        $this->assertSame(136000, $contract['remaining_cents']);
        $this->assertSame(['Extra trap', 'Extra pipe'], array_column($contract['accepted_lines'], 'title'));
        $this->assertFalse(collect($contract['requests'])->firstWhere('id', $draft->id)['counts_toward_contract']);
        $this->assertFalse(collect($contract['requests'])->firstWhere('id', $submitted->id)['counts_toward_contract']);
        $this->assertFalse(collect($contract['requests'])->firstWhere('id', $approvedOnly->id)['counts_toward_contract']);
        $this->assertFalse(collect($contract['requests'])->firstWhere('id', $rejected->id)['counts_toward_contract']);
        $this->assertFalse(collect($contract['requests'])->firstWhere('id', $declined->id)['counts_toward_contract']);

        $canceled = $this->openRequest($booking, 'Canceled extra', '40.00', '1', '40.00');
        $this->submit($booking, $canceled);
        $this->approve($booking, $canceled);
        $this->accept($booking, $canceled, 1);
        $canceled->update(['status' => 'canceled']);
        $afterCancel = app(ContractAmountCalculator::class)->summarize($booking->fresh());
        $this->assertSame(136000, $afterCancel['contract_cents']);
        $this->assertFalse(collect($afterCancel['requests'])->firstWhere('id', $canceled->id)['counts_toward_contract']);

        $this->actingAs($admin)->put(route('admin.quotations.update', $booking), [
            'quotation_request_id' => $second->id,
            'notes' => 'Narrower pipe work.',
            'items' => [[
                'item_type' => 'labor',
                'description' => 'Shorter pipe',
                'quantity' => '1',
                'unit' => 'piece',
                'unit_price' => '10.00',
            ]],
        ])->assertRedirect();

        $firstQuote->refresh();
        $secondQuote->refresh();
        $replacement = $second->quotations()->where('version_number', 2)->firstOrFail();
        $this->assertSame('approved', $firstQuote->status);
        $this->assertSame('160.00', $firstQuote->total_amount);
        $this->assertTrue($firstQuote->is_current);
        $this->assertSame('approved', $secondQuote->status);
        $this->assertFalse($secondQuote->is_current);
        $this->assertSame('350.00', $secondQuote->total_amount);
        $this->assertSame('submitted', $replacement->status);
        $this->assertTrue($replacement->is_current);
        $this->assertNull($replacement->customer_approved_at);
        $adjusted = app(ContractAmountCalculator::class)->summarize($booking->fresh());
        $this->assertSame(85000 + 16000, $adjusted['contract_cents']);
        $this->assertSame($financial, $this->financial($booking->fresh()));
        $this->assertSame($payments, Payment::query()->where('booking_id', $booking->id)->count());
        $this->assertFalse(Schema::hasTable('payouts'));

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('Original advertised rate')
            ->assertSee('₱850.00')
            ->assertSee('Extra trap')
            ->assertSee('₱160.00')
            ->assertSee('₱1,010.00')
            ->assertSee('Remaining amount payable')
            ->assertDontSee('Shorter pipe')
            ->assertDontSee('Narrower pipe work.');

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Extra trap')
            ->assertSee('The customer accepted this quotation')
            ->assertDontSee('Download receipt');

        $this->actingAs($admin)
            ->get(route('admin.quotations.show', $booking))
            ->assertOk()
            ->assertSee('Total contract amount')
            ->assertSee('₱1,010.00')
            ->assertSee('Extra pipe')
            ->assertSee('Shorter pipe');

        $this->actingAs($worker)->put(route('jobs.quotation.update', $booking), [
            'quotation_request_id' => $first->id,
            'items' => [[
                'item_type' => 'labor',
                'description' => 'Changed accepted work',
                'quantity' => '1',
                'unit' => 'hour',
                'unit_price' => '1.00',
            ]],
        ])->assertForbidden();
        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking), [
            'quotation_request_id' => $first->id,
        ])->assertForbidden();
        $this->actingAs($worker)->post(route('admin.quotations.approve', $booking), [
            'quotation_request_id' => $approvedOnly->id,
        ])->assertForbidden();
        $this->actingAs($worker)->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $approvedOnly->id,
            'version_number' => 1,
        ])->assertForbidden();
        $this->assertSame('160.00', $firstQuote->fresh()->total_amount);

        $service = $booking->service;
        $service->price_amount = '1000.00';
        $service->save();
        $booking->refresh();
        $this->assertSame('850.00', $booking->price_amount);
        $this->assertSame(85000, app(ContractAmountCalculator::class)->summarize($booking)['original_rate_cents']);
        $this->assertSame($financial, $this->financial($booking));
    }

    public function test_a_customer_reviews_each_additional_quotation_on_its_own(): void
    {
        $booking = $this->booking();
        $other = $this->booking();
        $customer = $this->customer();
        $first = $this->openRequest($booking, 'First extra', '20.00', '1', '20.00');
        $second = $this->openRequest($booking, 'Second extra', '30.00', '1', '30.00');
        $third = $this->openRequest($booking, 'Third extra', '40.00', '1', '40.00');
        $this->submit($booking, $first);
        $this->submit($booking, $second);
        $this->submit($booking, $third);
        $this->approve($booking, $first);
        $this->approve($booking, $second);
        $this->approve($booking, $third);

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'version_number' => 1,
        ])->assertForbidden();
        $foreign = $this->openRequest($other, 'Other home', '15.00', '1', '15.00');
        $this->submit($other, $foreign);
        $this->approve($other, $foreign);

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $first->id,
            'version_number' => 1,
        ])->assertRedirect();
        $this->actingAs($customer)->post(route('bookings.quotation.reject', $booking), [
            'quotation_request_id' => $third->id,
            'version_number' => 1,
            'customer_rejection_reason' => 'Skip this extra.',
        ])->assertRedirect();

        $this->assertSame('approved', $first->currentQuotation()->firstOrFail()->status);
        $this->assertSame('admin_approved', $second->currentQuotation()->firstOrFail()->status);
        $this->assertSame('customer_rejected', $third->currentQuotation()->firstOrFail()->status);
        $this->assertSame('admin_approved', $foreign->currentQuotation()->firstOrFail()->status);

        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $foreign->id,
            'version_number' => 1,
        ])->assertForbidden();
        $this->actingAs($customer)->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $third->id,
            'version_number' => 1,
        ])->assertForbidden();

        $this->actingAs($customer)
            ->get(route('bookings.quotation.show', $booking))
            ->assertOk()
            ->assertSee('First extra')
            ->assertSee('Second extra')
            ->assertSee('Third extra')
            ->assertSee('Skip this extra.')
            ->assertSee('Accept quotation')
            ->assertSee('₱870.00');
    }

    public function test_a_worker_can_revise_a_rejected_request_without_changing_an_accepted_one(): void
    {
        $booking = $this->booking();
        $worker = $this->worker();
        $accepted = $this->openRequest($booking, 'Accepted extra', '50.00', '1', '50.00');
        $this->submit($booking, $accepted);
        $this->approve($booking, $accepted);
        $this->accept($booking, $accepted, 1);
        $rejected = $this->openRequest($booking, 'Rejected extra', '25.00', '1', '25.00');
        $this->submit($booking, $rejected);
        $this->actingAs($this->admin())->post(route('admin.quotations.reject', $booking), [
            'quotation_request_id' => $rejected->id,
            'rejection_reason' => 'Price is high.',
        ])->assertRedirect();

        $this->actingAs($worker)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Price is high.')
            ->assertSee('Prepare a revision');

        $this->actingAs($worker)->post(route('jobs.quotation.revise', $booking), [
            'quotation_request_id' => $rejected->id,
        ])->assertRedirect();

        $acceptedQuote = $accepted->currentQuotation()->firstOrFail();
        $revision = $rejected->quotations()->where('version_number', 2)->firstOrFail();
        $this->assertSame('approved', $acceptedQuote->status);
        $this->assertSame('50.00', $acceptedQuote->total_amount);
        $this->assertTrue($acceptedQuote->is_current);
        $this->assertSame('rejected', $rejected->quotations()->where('version_number', 1)->firstOrFail()->status);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($rejected->id, $revision->quotation_request_id);
        $this->assertSame(2, $revision->version_number);
    }

    public function test_existing_quotation_rows_keep_their_versions_when_grouped(): void
    {
        $booking = $this->booking();
        $financial = $this->financial($booking);

        DB::table('quotations')->insert([
            [
                'booking_id' => $booking->id,
                'version_number' => 1,
                'status' => 'approved',
                'subtotal' => '80.00',
                'total_amount' => '80.00',
                'currency' => 'PHP',
                'is_current' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'booking_id' => $booking->id,
                'version_number' => 2,
                'status' => 'draft',
                'subtotal' => '90.00',
                'total_amount' => '90.00',
                'currency' => 'PHP',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        QuotationRequestGrouper::groupUnassigned();

        $request = QuotationRequest::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(1, $request->request_number);
        $this->assertSame(2, Quotation::query()->where('quotation_request_id', $request->id)->count());
        $this->assertSame('80.00', Quotation::query()->where('version_number', 1)->where('quotation_request_id', $request->id)->firstOrFail()->total_amount);
        $this->assertSame($request->id, Quotation::query()->where('booking_id', $booking->id)->where('is_current', true)->firstOrFail()->current_request_id);
        $this->assertSame($financial, $this->financial($booking->fresh()));
        $this->assertSame('850.00', $booking->fresh()->price_amount);
    }

    public function test_contract_math_uses_integer_cents(): void
    {
        $booking = $this->booking();
        $request = $this->openRequest($booking, 'Cents', '10.10', '1', '10.10');
        $this->submit($booking, $request);
        $this->approve($booking, $request);
        $this->accept($booking, $request, 1);

        $contract = app(ContractAmountCalculator::class)->summarize($booking->fresh());
        $this->assertSame(Money::toCents('850.00') + Money::toCents('10.10'), $contract['contract_cents']);
        $this->assertSame('860.10', $contract['contract_amount']);
    }

    private function openRequest(Booking $booking, string $title, string $amount, string $quantity, string $unitPrice): QuotationRequest
    {
        $this->actingAs($this->worker())->post(route('jobs.quotation.store', $booking), [
            'title' => $title,
            'items' => [[
                'item_type' => 'labor',
                'description' => $title,
                'quantity' => $quantity,
                'unit' => 'hour',
                'unit_price' => $unitPrice,
            ]],
        ])->assertRedirect();

        $request = QuotationRequest::query()->where('booking_id', $booking->id)->where('title', $title)->firstOrFail();
        $this->assertSame($amount, $request->currentQuotation()->firstOrFail()->total_amount);

        return $request;
    }

    private function submit(Booking $booking, QuotationRequest $request): void
    {
        $this->actingAs($this->worker())->post(route('jobs.quotation.submit', $booking), [
            'quotation_request_id' => $request->id,
        ])->assertRedirect();
    }

    private function approve(Booking $booking, QuotationRequest $request): void
    {
        $this->actingAs($this->admin())->post(route('admin.quotations.approve', $booking), [
            'quotation_request_id' => $request->id,
        ])->assertRedirect();
    }

    private function accept(Booking $booking, QuotationRequest $request, int $version): void
    {
        $this->actingAs($this->customer())->post(route('bookings.quotation.accept', $booking), [
            'quotation_request_id' => $request->id,
            'version_number' => $version,
        ])->assertRedirect();
    }

    private function customer(): User
    {
        return User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
    }

    private function worker(): User
    {
        return User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
    }

    private function booking(): Booking
    {
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $location = $worker->locations()->firstOrFail();

        $this->actingAs($this->customer())->post(route('bookings.store', $worker), [
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
