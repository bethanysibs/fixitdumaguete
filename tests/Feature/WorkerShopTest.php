<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerRating;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoMarketplaceSeeder::class);
    }

    public function test_the_shop_lists_every_sample_professional_away_from_the_homepage(): void
    {
        $home = $this->get(route('home'));

        $home->assertOk();
        $home->assertDontSee('A sample professional for each service');
        $home->assertDontSee('Andres Villanueva');
        $home->assertSee(route('workers.index', ['category' => 'plumbing']), false);

        $shop = $this->get(route('workers.index'));

        $shop->assertOk();
        $shop->assertSee('A sample professional for each service');
        $shop->assertSee('No ratings yet');
        $shop->assertSee('11 professionals');

        foreach (config('brand.services') as $service) {
            $shop->assertSee($service['name']);
        }

        $this->assertSame(11, WorkerProfile::query()->where('is_sample', true)->count());
    }

    public function test_search_category_area_and_price_narrow_the_shop(): void
    {
        $this->get(route('workers.index', ['q' => 'Liza']))
            ->assertOk()
            ->assertSee('Liza Mercado')
            ->assertDontSee('Andres Villanueva')
            ->assertSee('1 professional');

        $this->get(route('workers.index', ['category' => 'plumbing']))
            ->assertOk()
            ->assertSee('Andres Villanueva')
            ->assertDontSee('Liza Mercado');

        $this->get(route('workers.index', ['location' => 'dauin']))
            ->assertOk()
            ->assertSee('Paolo Ramirez')
            ->assertSee('Miguel Tan')
            ->assertSee('Rico Dela Cruz')
            ->assertDontSee('Andres Villanueva');

        $priced = $this->get(route('workers.index', ['min_price' => '1000', 'max_price' => '1300']));

        $priced->assertOk();
        $priced->assertSee('Carmen Uy');
        $priced->assertSee('Rico Dela Cruz');
        $priced->assertDontSee('Andres Villanueva');
        $priced->assertDontSee('Miguel Tan');

        $sorted = $this->get(route('workers.index', ['sort' => 'price_asc']));
        $content = $sorted->getContent();

        $this->assertLessThan(strpos($content, 'Rosa Bautista'), strpos($content, 'Joel Navarro'));
        $this->assertLessThan(strpos($content, 'Andres Villanueva'), strpos($content, 'Rosa Bautista'));
    }

    public function test_an_invalid_price_range_is_rejected(): void
    {
        $this->get(route('workers.index', ['min_price' => '900', 'max_price' => '100']))
            ->assertSessionHasErrors('max_price');
    }

    public function test_a_service_link_opens_the_shop_filtered_to_that_job(): void
    {
        $category = ServiceCategory::query()->where('slug', 'plumbing')->firstOrFail();

        $this->get(route('services.show', $category))
            ->assertRedirect(route('workers.index', ['category' => 'plumbing']));
    }

    public function test_customers_and_admins_can_set_star_ratings(): void
    {
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();
        $customer = User::query()->where('email', 'customer@fixitdumaguete.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@fixitdumaguete.test')->firstOrFail();
        $sampleWorker = $worker->user;

        $this->put(route('workers.rating.update', $worker), ['score' => 5])->assertRedirect(route('login'));

        $this->actingAs($sampleWorker)
            ->put(route('workers.rating.update', $worker), ['score' => 5])
            ->assertForbidden();

        $this->actingAs($customer)
            ->put(route('workers.rating.update', $worker), ['score' => 4])
            ->assertRedirect();

        $this->actingAs($customer)
            ->put(route('workers.rating.update', $worker), ['score' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, WorkerRating::query()->count());
        $this->assertSame(5, $worker->ratings()->first()->score);

        $this->actingAs($customer)
            ->get(route('workers.show', $worker))
            ->assertSee('5.0 out of 5, 1 rating');

        $this->actingAs($customer)
            ->put(route('workers.rating.update', $worker), ['score' => 9])
            ->assertSessionHasErrors('score');

        $rating = $worker->ratings()->first();

        $this->actingAs($admin)
            ->put(route('workers.rating.revise', [$worker, $rating]), ['score' => 3])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, $rating->fresh()->score);

        $this->actingAs($customer)
            ->put(route('workers.rating.revise', [$worker, $rating]), ['score' => 1])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('workers.rating.destroy', [$worker, $rating]))
            ->assertRedirect();

        $this->assertSame(0, WorkerRating::query()->count());

        $this->get(route('workers.show', $worker))->assertSee('No ratings yet');
    }

    public function test_a_customer_can_log_in_and_return_to_the_profile(): void
    {
        $worker = WorkerProfile::query()->where('slug', 'andres-villanueva')->firstOrFail();

        $this->get(route('workers.show', $worker));
        $this->get(route('login'))->assertOk();

        $this->post(route('login.store'), [
            'email' => 'customer@fixitdumaguete.test',
            'password' => 'password',
        ])->assertRedirect(route('workers.show', $worker));

        $this->assertAuthenticated();
    }
}
