<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CommissionSetting;
use App\Models\Payment;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Services\CommissionCalculator;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_only_an_admin_can_save_commission_settings(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)->get(route('admin.commission.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.commission.store'), $this->version())->assertForbidden();
        $this->actingAs($worker)->post(route('admin.commission.store'), $this->version())->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.commission.store'), $this->version([
                'first_booking_rate' => '12.00',
                'repeat_booking_rate' => '4.50',
            ]))
            ->assertRedirect(route('admin.commission.index'));

        $this->assertDatabaseHas('commission_settings', [
            'first_booking_rate' => '12.00',
            'repeat_booking_rate' => '4.50',
        ]);
    }

    public function test_a_first_booking_snapshots_the_first_rate_and_ignores_a_submitted_rate(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $booking = $this->book($customer, $worker, ['commission_rate' => '1.00']);

        $this->assertSame('first', $booking->commission_rule);
        $this->assertSame('entire_service', $booking->commission_basis);
        $this->assertSame('850.00', $booking->commission_base_amount);
        $this->assertSame('10.00', $booking->commission_original_rate);
        $this->assertSame('10.00', $booking->commission_rate);
        $this->assertNull($booking->commission_adjustment);
        $this->assertSame('85.00', $booking->commission_amount);
        $this->assertSame('765.00', $booking->worker_amount);
        $this->assertNotNull($booking->commission_setting_id);
    }

    public function test_only_a_completed_paid_booking_makes_the_next_one_a_repeat(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $pending = $this->book($customer, $worker);
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);

        $pending->update(['status' => 'canceled']);
        Payment::query()->create([
            'booking_id' => $pending->id,
            'customer_id' => $customer->id,
            'amount' => $pending->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);

        $confirmed = $this->book($customer, $worker);
        $confirmed->update(['status' => 'confirmed']);
        Payment::query()->create([
            'booking_id' => $confirmed->id,
            'customer_id' => $customer->id,
            'amount' => $confirmed->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);

        $refunded = $this->book($customer, $worker);
        $refunded->update(['status' => 'completed']);
        Payment::query()->create([
            'booking_id' => $refunded->id,
            'customer_id' => $customer->id,
            'amount' => $refunded->price_amount,
            'status' => 'refunded',
            'method' => 'platform',
            'paid_at' => now(),
        ]);
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);

        $completed = $this->book($customer, $worker);
        $completed->update(['status' => 'completed']);
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);

        Payment::query()->create([
            'booking_id' => $completed->id,
            'customer_id' => $customer->id,
            'amount' => $completed->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);

        $repeat = $this->book($customer, $worker);
        $this->assertSame('repeat', $repeat->commission_rule);
        $this->assertSame('5.00', $repeat->commission_original_rate);
        $this->assertSame('42.50', $repeat->commission_amount);
        $this->assertSame('807.50', $repeat->worker_amount);
        $this->assertSame('850.00', $repeat->commission_base_amount);
        $this->assertNull($repeat->commission_adjustment);
    }

    public function test_a_category_rate_and_a_worker_rate_override_the_first_booking_rate(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $category = ServiceCategory::query()->where('slug', 'plumbing')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'category_rates' => [$category->id => '12.00'],
        ]))->assertRedirect();

        $categoryBooking = $this->book($customer, $worker);
        $this->assertSame('category', $categoryBooking->commission_rule);
        $this->assertSame('12.00', $categoryBooking->commission_original_rate);
        $this->assertSame('102.00', $categoryBooking->commission_amount);

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'category_rates' => [$category->id => '12.00'],
            'worker_rates' => [$worker->id => '3.00'],
        ]))->assertRedirect();

        $workerBooking = $this->book($customer, $worker);
        $this->assertSame('worker', $workerBooking->commission_rule);
        $this->assertSame('3.00', $workerBooking->commission_original_rate);
        $this->assertSame('25.50', $workerBooking->commission_amount);
        $this->assertSame('824.50', $workerBooking->worker_amount);
    }

    public function test_minimum_and_maximum_adjust_the_final_commission_only(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'minimum_amount' => '100.00',
        ]))->assertRedirect();

        $raised = $this->book($customer, $worker);
        $this->assertSame('10.00', $raised->commission_original_rate);
        $this->assertSame('15.00', $raised->commission_adjustment);
        $this->assertSame('100.00', $raised->commission_amount);
        $this->assertSame('750.00', $raised->worker_amount);

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'maximum_amount' => '50.00',
        ]))->assertRedirect();

        $lowered = $this->book($customer, $worker);
        $this->assertSame('10.00', $lowered->commission_original_rate);
        $this->assertSame('-35.00', $lowered->commission_adjustment);
        $this->assertSame('50.00', $lowered->commission_amount);
        $this->assertSame('800.00', $lowered->worker_amount);
    }

    public function test_zero_percent_full_percent_and_a_minimum_above_the_price(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'first_booking_rate' => '0.00',
        ]))->assertRedirect();

        $free = $this->book($customer, $worker);
        $this->assertSame('0.00', $free->commission_original_rate);
        $this->assertNull($free->commission_adjustment);
        $this->assertSame('0.00', $free->commission_amount);
        $this->assertSame('850.00', $free->worker_amount);

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'first_booking_rate' => '100.00',
        ]))->assertRedirect();

        $full = $this->book($customer, $worker);
        $this->assertSame('100.00', $full->commission_original_rate);
        $this->assertNull($full->commission_adjustment);
        $this->assertSame('850.00', $full->commission_amount);
        $this->assertSame('0.00', $full->worker_amount);

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'first_booking_rate' => '10.00',
            'minimum_amount' => '1000.00',
        ]))->assertRedirect();

        $capped = $this->book($customer, $worker);
        $this->assertSame('10.00', $capped->commission_original_rate);
        $this->assertSame('entire_service', $capped->commission_basis);
        $this->assertSame('850.00', $capped->commission_base_amount);
        $this->assertSame('765.00', $capped->commission_adjustment);
        $this->assertSame('850.00', $capped->commission_amount);
        $this->assertSame('0.00', $capped->worker_amount);
    }

    public function test_labor_bases_cannot_be_saved_and_snapshots_keep_the_service_price(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.commission.index'))
            ->assertOk()
            ->assertDontSee('Default percentage')
            ->assertSee('Labor amount — not available yet', false)
            ->assertSee('value="labor" disabled', false);

        $this->assertFalse(Schema::hasColumn('commission_settings', 'default_rate'));

        $before = CommissionSetting::query()->count();

        foreach (['labor', 'labor_and_charges'] as $basis) {
            $this->actingAs($admin)
                ->post(route('admin.commission.store'), $this->version(['basis' => $basis]))
                ->assertSessionHasErrors('basis');
        }

        $this->assertSame($before, CommissionSetting::query()->count());

        CommissionSetting::query()->create([
            'effective_at' => now(),
            'first_booking_rate' => '10.00',
            'repeat_booking_rate' => '5.00',
            'basis' => 'labor',
            'created_by' => $admin->id,
        ]);

        $booking = $this->book($customer, $worker);
        $this->assertSame('entire_service', $booking->commission_basis);
        $this->assertSame('850.00', $booking->commission_base_amount);
        $this->assertSame('85.00', $booking->commission_amount);
        $this->assertSame('765.00', $booking->worker_amount);
    }

    public function test_a_refunded_payment_does_not_qualify_and_a_second_payment_is_rejected(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $calculator = app(CommissionCalculator::class);

        $booking = $this->book($customer, $worker);
        $booking->update(['status' => 'completed']);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'amount' => $booking->price_amount,
            'status' => 'paid',
            'method' => 'platform',
            'paid_at' => now(),
        ]);

        $this->assertTrue($calculator->hasCompletedPaidBooking($customer->id, $worker->id));

        try {
            Payment::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'amount' => $booking->price_amount,
                'status' => 'refunded',
                'method' => 'platform',
                'paid_at' => now(),
            ]);
            $this->fail('A booking stored a second payment.');
        } catch (QueryException) {
            $this->assertSame(1, Payment::query()->where('booking_id', $booking->id)->count());
        }

        $payment->update(['status' => 'refunded']);

        $this->assertFalse($calculator->hasCompletedPaidBooking($customer->id, $worker->id));
        $this->assertSame('first', $this->book($customer, $worker)->commission_rule);
    }

    public function test_a_new_commission_version_does_not_change_an_existing_booking(): void
    {
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $booking = $this->book($customer, $worker);
        $settingId = $booking->commission_setting_id;

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'first_booking_rate' => '20.00',
            'effective_at' => now()->addMinute()->format('Y-m-d H:i:s'),
        ]))->assertRedirect();

        $booking->refresh();
        $this->assertSame($settingId, $booking->commission_setting_id);
        $this->assertSame('10.00', $booking->commission_original_rate);
        $this->assertSame('85.00', $booking->commission_amount);
        $this->assertSame('765.00', $booking->worker_amount);

        $this->actingAs($admin)->post(route('admin.commission.store'), $this->version([
            'first_booking_rate' => '20.00',
        ]))->assertRedirect();

        $booking->refresh();
        $this->assertSame('85.00', $booking->commission_amount);
        $this->assertSame('first', $booking->commission_rule);

        $later = $this->book($customer, $worker);
        $this->assertSame('20.00', $later->commission_original_rate);
        $this->assertSame('170.00', $later->commission_amount);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function version(array $overrides = []): array
    {
        return array_merge([
            'effective_at' => now()->format('Y-m-d H:i:s'),
            'first_booking_rate' => '10.00',
            'repeat_booking_rate' => '5.00',
            'basis' => 'entire_service',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(User $customer, WorkerProfile $worker, array $overrides = []): Booking
    {
        $location = $worker->locations()->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.store', $worker), array_merge([
            'service_id' => $worker->services()->firstOrFail()->id,
            'location_id' => $location->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'notes' => 'The kitchen faucet is dripping.',
            'landmark' => 'Blue gate beside the corner store',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => $location->map_latitude,
            'longitude' => $location->map_longitude,
        ], $overrides))->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }
}
