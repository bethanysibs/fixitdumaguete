<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | Public name, tagline, and image paths live here so views do not
    | hardcode the marketplace brand. Paths are relative to public/.
    |
    */

    'name' => env('APP_NAME', 'FixItDumaguete'),

    'tagline' => 'Find trusted local professionals near you.',

    'logo' => 'brand/mark.svg',

    'favicon' => 'brand/mark.svg',

    'colors' => [
        'primary' => '#0B1F3A',
        'secondary' => '#E36A1B',
        'white' => '#FFFFFF',
        'light' => '#F6F3EE',
        'dark' => '#243140',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public navigation
    |--------------------------------------------------------------------------
    |
    | Fragment names are appended to the homepage URL. Account links are
    | added when authentication exists.
    |
    */

    'nav' => [
        ['label' => 'Services', 'fragment' => 'services'],
        ['label' => 'Workers', 'route' => 'workers.index'],
        ['label' => 'How it works', 'fragment' => 'how-it-works'],
        ['label' => 'Locations', 'fragment' => 'locations'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Homepage copy
    |--------------------------------------------------------------------------
    |
    | These lists describe the marketplace being built. They are not the
    | service-category or location records. Those tables arrive in a later
    | phase and will replace this copy on the public catalog pages.
    |
    */

    'services' => [
        [
            'name' => 'Plumbing',
            'icon' => 'bi-droplet',
            'summary' => 'Leaks, clogged drains, fixtures, and other household water repairs.',
        ],
        [
            'name' => 'Electrical Services',
            'icon' => 'bi-lightning-charge',
            'summary' => 'Outlets, lighting, fans, and other residential electrical work.',
        ],
        [
            'name' => 'Carpentry',
            'icon' => 'bi-hammer',
            'summary' => 'Doors, cabinets, furniture repairs, and small woodwork jobs.',
        ],
        [
            'name' => 'Aircon Cleaning and Repair',
            'icon' => 'bi-snow',
            'summary' => 'Cleaning, basic servicing, and repair for home air-conditioning units.',
        ],
        [
            'name' => 'House Cleaning',
            'icon' => 'bi-stars',
            'summary' => 'Regular home cleaning and one-time cleanups for houses and apartments.',
        ],
        [
            'name' => 'Painting',
            'icon' => 'bi-brush',
            'summary' => 'Interior and exterior painting for rooms, gates, and small surfaces.',
        ],
        [
            'name' => 'Handyman Services',
            'icon' => 'bi-tools',
            'summary' => 'Mixed repair jobs that do not need a single specialist.',
        ],
        [
            'name' => 'Appliance Repair',
            'icon' => 'bi-plug',
            'summary' => 'Help with common household appliances that have stopped working properly.',
        ],
        [
            'name' => 'Pest Control',
            'icon' => 'bi-bug',
            'summary' => 'Treatment for household pests in homes and small properties.',
        ],
        [
            'name' => 'Landscaping',
            'icon' => 'bi-tree',
            'summary' => 'Yard cleanup, trimming, and basic garden care.',
        ],
        [
            'name' => 'Other Home Services',
            'icon' => 'bi-house-gear',
            'summary' => 'Other practical home jobs that do not fit a single trade above.',
        ],
    ],

    'steps' => [
        [
            'title' => 'Describe the job',
            'summary' => 'Say what you need done, where the home is, and the time that suits you.',
        ],
        [
            'title' => 'Choose a verified worker',
            'summary' => 'Review local professionals who cover your municipality and accept the kind of work you need.',
        ],
        [
            'title' => 'Book on the platform',
            'summary' => 'Confirm the visit, pay through FixItDumaguete, and keep the booking in your account.',
        ],
    ],

    'promises' => [
        [
            'icon' => 'bi-patch-check',
            'title' => 'Verification before booking',
            'summary' => 'A worker can be booked only after the platform has approved that worker.',
        ],
        [
            'icon' => 'bi-geo-alt',
            'title' => 'Local coverage',
            'summary' => 'The first release is built for Dumaguete City, Sibulan, and Dauin.',
        ],
        [
            'icon' => 'bi-wallet2',
            'title' => 'Payment on the platform',
            'summary' => 'Customer payments, commissions, and worker earnings stay in the marketplace record.',
        ],
    ],

    'locations' => [
        [
            'name' => 'Dumaguete City',
            'province' => 'Negros Oriental',
            'summary' => 'The city proper, including houses, apartments, and small shops across its barangays.',
            'map_latitude' => '9.3068400',
            'map_longitude' => '123.3054470',
        ],
        [
            'name' => 'Sibulan',
            'province' => 'Negros Oriental',
            'summary' => 'The municipality north of Dumaguete, from the poblacion toward the airport and the coast.',
            'map_latitude' => '9.3583000',
            'map_longitude' => '123.2847000',
        ],
        [
            'name' => 'Dauin',
            'province' => 'Negros Oriental',
            'summary' => 'The municipality south of Dumaguete, including residential streets and coastal communities.',
            'map_latitude' => '9.1907000',
            'map_longitude' => '123.2658000',
        ],
    ],

    'seo' => [
        'title' => 'FixItDumaguete | Trusted home services in Dumaguete, Sibulan, and Dauin',
        'description' => 'FixItDumaguete connects households in Dumaguete City, Sibulan, and Dauin with local plumbers, electricians, carpenters, cleaners, and other home professionals.',
    ],

];
