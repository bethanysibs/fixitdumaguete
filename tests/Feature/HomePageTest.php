<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_the_public_marketplace_shell(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(config('brand.name'));
        $response->assertSee(config('brand.tagline'));
        $response->assertSee('<h1', false);
        $response->assertSee('Skip to content', false);
        $this->assertTrue(
            str_contains($response->getContent(), 'build/assets')
            || str_contains($response->getContent(), '/resources/css/app.scss'),
            'The layout should load the built stylesheet or the Vite dev server.'
        );
        $response->assertDontSee('A sample professional for each service');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_homepage_lists_the_initial_services_and_locations(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();

        foreach (config('brand.services') as $service) {
            $response->assertSee($service['name']);
            $response->assertSee($service['summary']);
        }

        foreach (config('brand.locations') as $location) {
            $response->assertSee($location['name']);
            $response->assertSee($location['province']);
            $response->assertSee($location['summary']);
        }
    }

    public function test_homepage_publishes_indexable_metadata_and_valid_structured_data(): void
    {
        $response = $this->get(route('home'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('<title>'.e(config('brand.seo.title')).'</title>', false);
        $response->assertSee('name="description" content="'.e(config('brand.seo.description')).'"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:url"', false);
        $response->assertSee('name="twitter:card" content="summary"', false);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        $this->assertArrayHasKey(1, $matches);

        $data = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Organization', $data['@graph'][0]['@type']);
        $this->assertSame('WebSite', $data['@graph'][1]['@type']);
        $this->assertSame(config('brand.name'), $data['@graph'][0]['name']);
        $this->assertArrayNotHasKey('aggregateRating', $data['@graph'][0]);
        $this->assertArrayNotHasKey('address', $data['@graph'][0]);
        $this->assertArrayNotHasKey('telephone', $data['@graph'][0]);
    }

    public function test_account_and_admin_routes_are_not_published_yet(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }
}
