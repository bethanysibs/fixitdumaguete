<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_a_customer_can_book_and_record_a_platform_payment(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $service = $worker->services()->firstOrFail();
        $location = $worker->locations()->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.store', $worker), [
            'service_id' => $service->id,
            'location_id' => $location->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'The kitchen faucet is dripping.',
            'landmark' => 'Blue gate beside the corner store',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => $location->map_latitude,
            'longitude' => $location->map_longitude,
        ])->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('850.00', $booking->price_amount);
        $this->assertSame('10.00', $booking->commission_rate);
        $this->assertSame('85.00', $booking->commission_amount);
        $this->assertSame('765.00', $booking->worker_amount);

        $this->actingAs($customer)
            ->post(route('bookings.pay', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment->status);
        $this->assertSame('850.00', $booking->payment->amount);
        $this->assertSame('platform', $booking->payment->method);

        $this->actingAs($customer)
            ->post(route('bookings.pay', $booking))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Paid');
    }

    public function test_a_customer_can_cancel_before_paying_and_cannot_book_outside_the_service_area(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $service = $worker->services()->firstOrFail();
        $otherLocation = Location::query()->where('slug', 'dauin')->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.store', $worker), [
            'service_id' => $service->id,
            'location_id' => $otherLocation->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'Wrong town.',
            'landmark' => 'Blue gate',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => $otherLocation->map_latitude,
            'longitude' => $otherLocation->map_longitude,
        ])->assertSessionHasErrors('location_id');

        $this->actingAs($customer)->post(route('bookings.store', $worker), [
            'service_id' => $service->id,
            'location_id' => $worker->locations()->firstOrFail()->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'notes' => 'The pin is in the wrong place.',
            'landmark' => 'Blue gate',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => '14.5995000',
            'longitude' => '120.9842000',
        ])->assertSessionHasErrors('latitude');

        $booking = $this->book($customer, $worker);

        $this->actingAs($customer)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('canceled', $booking->fresh()->status);
        $this->assertNull($booking->payment);
    }

    public function test_guests_and_workers_cannot_create_a_booking(): void
    {
        $workerUser = User::query()->where('email', 'sample.andres-villanueva@fixitdumaguete.test')->firstOrFail();
        $worker = $workerUser->workerProfile;

        $this->get(route('bookings.create', $worker))->assertRedirect(route('login'));

        $this->actingAs($workerUser)
            ->get(route('bookings.create', $worker))
            ->assertForbidden();

        $worker->update(['verification_status' => 'pending']);
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();

        $this->actingAs($customer)
            ->get(route('bookings.create', $worker))
            ->assertNotFound();
    }

    public function test_an_admin_can_mark_a_paid_booking_done_or_canceled(): void
    {
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $booking = $this->book($customer, $worker);

        $this->actingAs($customer)->post(route('bookings.pay', $booking));

        $this->actingAs($worker->user)
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Maya Cruz')
            ->assertSee('₱765.00');

        $this->actingAs($customer)
            ->put(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $booking), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->payment->fresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $booking), ['status' => 'canceled'])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('canceled', $booking->status);
        $this->assertSame('refunded', $booking->payment()->first()->status);

        $this->actingAs($admin)
            ->get(route('admin.bookings.index', ['status' => 'canceled']))
            ->assertOk()
            ->assertSee('Plumbing')
            ->assertSee('Refunded');
    }

    private function book(User $customer, WorkerProfile $worker): Booking
    {
        $location = $worker->locations()->firstOrFail();

        $this->actingAs($customer)->post(route('bookings.store', $worker), [
            'service_id' => $worker->services()->firstOrFail()->id,
            'location_id' => $location->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'notes' => 'Please check the sink trap.',
            'landmark' => 'Blue gate beside the corner store',
            'address' => 'Rizal Boulevard, Dumaguete City',
            'contact_phone' => '09171234567',
            'latitude' => $location->map_latitude,
            'longitude' => $location->map_longitude,
        ])->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }
}
