<?php

namespace Tests\Feature;

use App\Models\WorkerProfile;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleWorkersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_profile_shows_the_trade_area_and_price_without_private_details(): void
    {
        $this->seed(DemoMarketplaceSeeder::class);

        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $service = $worker->services()->firstOrFail();

        $response = $this->get(route('workers.show', $worker));

        $response->assertOk();
        $response->assertSee('Andres Villanueva');
        $response->assertSee('Plumbing');
        $response->assertSee('Dumaguete City');
        $response->assertSee($service->formattedPrice());
        $response->assertSee('Sample profile');
        $response->assertSee('No ratings yet');
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
        $response->assertDontSee('aggregateRating', false);
        $response->assertDontSee('application/ld+json', false);
        $response->assertDontSee('@fixitdumaguete.test');
        $this->assertDoesNotMatchRegularExpression('/(?:\+63|09\d{9}|\(\d{3}\)\s*\d{3})/', $response->getContent());
    }

    public function test_unknown_worker_profiles_are_not_found(): void
    {
        $this->get(route('workers.show', 'missing-worker'))->assertNotFound();
    }
}
