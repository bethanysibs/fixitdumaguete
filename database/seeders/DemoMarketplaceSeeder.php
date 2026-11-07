<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccounts();

        $locations = $this->seedLocations();
        $categories = $this->seedCategories();

        foreach ($this->workers() as $categoryName => $worker) {
            $category = $categories[$categoryName] ?? null;
            $location = $locations[$worker['location']] ?? null;

            if ($category === null || $location === null) {
                throw new RuntimeException('Sample worker "'.$categoryName.'" does not match the catalog.');
            }

            $slug = Str::slug($worker['name']);

            $user = User::query()->updateOrCreate(
                ['email' => 'sample.'.$slug.'@fixitdumaguete.test'],
                [
                    'name' => $worker['name'],
                    'role' => 'worker',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $profile = WorkerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'slug' => $slug,
                    'headline' => $category->name.' in '.$location->name,
                    'bio' => 'Sample profile for the local preview. This is not a real FixItDumaguete worker, and this profile cannot be booked.',
                    'verification_status' => 'approved',
                    'is_sample' => true,
                ],
            );

            $profile->locations()->sync([$location->id]);

            Service::query()->updateOrCreate(
                ['slug' => $category->slug.'-'.$slug],
                [
                    'worker_profile_id' => $profile->id,
                    'service_category_id' => $category->id,
                    'title' => $category->name,
                    'summary' => $category->summary,
                    'price_amount' => $worker['price'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<string, Location>
     */
    private function seedLocations(): array
    {
        $locations = [];

        foreach (config('brand.locations') as $location) {
            $record = Location::query()->updateOrCreate(
                ['slug' => Str::slug($location['name'])],
                [
                    'name' => $location['name'],
                    'province' => $location['province'],
                    'summary' => $location['summary'],
                    'is_active' => true,
                ],
            );

            $locations[$record->name] = $record;
        }

        return $locations;
    }

    /**
     * @return array<string, ServiceCategory>
     */
    private function seedCategories(): array
    {
        $categories = [];
        $workers = $this->workers();

        foreach (config('brand.services') as $index => $service) {
            if (! isset($workers[$service['name']])) {
                throw new RuntimeException('Missing sample worker for '.$service['name'].'.');
            }

            $record = ServiceCategory::query()->updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [
                    'name' => $service['name'],
                    'icon' => $service['icon'],
                    'summary' => $service['summary'],
                    'display_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            $categories[$record->name] = $record;
        }

        return $categories;
    }

    /**
     * One fictional sample professional for each catalog category.
     * Prices stay decimal strings so they are never stored as floats.
     *
     * @return array<string, array{name: string, location: string, price: string}>
     */
    private function workers(): array
    {
        return [
            'Plumbing' => ['name' => 'Andres Villanueva', 'location' => 'Dumaguete City', 'price' => '850.00'],
            'Electrical Services' => ['name' => 'Liza Mercado', 'location' => 'Sibulan', 'price' => '750.00'],
            'Carpentry' => ['name' => 'Paolo Ramirez', 'location' => 'Dauin', 'price' => '900.00'],
            'Aircon Cleaning and Repair' => ['name' => 'Carmen Uy', 'location' => 'Dumaguete City', 'price' => '1200.00'],
            'House Cleaning' => ['name' => 'Rosa Bautista', 'location' => 'Sibulan', 'price' => '600.00'],
            'Painting' => ['name' => 'Miguel Tan', 'location' => 'Dauin', 'price' => '1500.00'],
            'Handyman Services' => ['name' => 'Joel Navarro', 'location' => 'Dumaguete City', 'price' => '500.00'],
            'Appliance Repair' => ['name' => 'Anita Cruz', 'location' => 'Sibulan', 'price' => '700.00'],
            'Pest Control' => ['name' => 'Rico Dela Cruz', 'location' => 'Dauin', 'price' => '1100.00'],
            'Landscaping' => ['name' => 'Benito Flores', 'location' => 'Dumaguete City', 'price' => '800.00'],
            'Other Home Services' => ['name' => 'Sara Gomez', 'location' => 'Sibulan', 'price' => '650.00'],
        ];
    }

    private function seedAccounts(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'customer@fixitdumaguete.test'],
            [
                'name' => 'Maya Cruz',
                'role' => 'customer',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@fixitdumaguete.test'],
            [
                'name' => 'Local Admin',
                'role' => 'admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
