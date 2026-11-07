<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_card_opens_the_workers_for_that_job(): void
    {
        $this->seed(DemoMarketplaceSeeder::class);

        $category = ServiceCategory::query()->where('slug', 'plumbing')->firstOrFail();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('workers.index', ['category' => $category->slug]), false);

        $this->get(route('services.show', $category))
            ->assertRedirect(route('workers.index', ['category' => 'plumbing']));

        $this->get(route('workers.index', ['category' => 'plumbing']))
            ->assertOk()
            ->assertSee('Andres Villanueva')
            ->assertSee('Plumbing')
            ->assertDontSee('Liza Mercado');
    }

    public function test_a_category_without_workers_shows_an_empty_state(): void
    {
        $category = ServiceCategory::query()->create([
            'name' => 'Painting',
            'slug' => 'painting',
            'icon' => 'bi-brush',
            'summary' => 'Interior and exterior painting.',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->get(route('services.show', $category))
            ->assertRedirect(route('workers.index', ['category' => 'painting']));

        $this->get(route('workers.index', ['category' => 'painting']))
            ->assertOk()
            ->assertSee('No professionals match these filters');
    }

    public function test_unknown_and_inactive_categories_are_not_found(): void
    {
        $category = ServiceCategory::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'icon' => 'bi-eye-slash',
            'summary' => 'Not offered.',
            'display_order' => 1,
            'is_active' => false,
        ]);

        $this->get(route('services.show', 'missing-service'))->assertNotFound();
        $this->get(route('services.show', $category))->assertNotFound();
    }
}
