<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $name = (string) config('brand.name');
        $url = url('/');

        return view('home', [
            'catalog' => $this->catalog(),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        'name' => $name,
                        'url' => $url,
                        'description' => config('brand.seo.description'),
                        'logo' => asset((string) config('brand.logo')),
                    ],
                    [
                        '@type' => 'WebSite',
                        'name' => $name,
                        'url' => $url,
                        'description' => config('brand.tagline'),
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return Collection<int, array{name: string, icon: string, summary: string, url: string|null}>
     */
    private function catalog(): Collection
    {
        $categories = ServiceCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        if ($categories->isEmpty()) {
            return collect(config('brand.services'))->map(fn (array $service) => [
                'name' => $service['name'],
                'icon' => $service['icon'],
                'summary' => $service['summary'],
                'url' => null,
            ])->values();
        }

        return $categories->map(fn (ServiceCategory $category) => [
            'name' => $category->name,
            'icon' => $category->icon,
            'summary' => $category->summary,
            'url' => route('workers.index', ['category' => $category->slug]),
        ])->values();
    }
}
