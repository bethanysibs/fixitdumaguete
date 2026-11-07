<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('brand.colors.primary') }}">
    <title>@yield('title', config('brand.seo.title'))</title>
    <meta name="description" content="@yield('meta_description', config('brand.seo.description'))">
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="icon" href="{{ asset(config('brand.favicon')) }}" type="image/svg+xml">
    <meta property="og:site_name" content="{{ config('brand.name') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', config('brand.seo.title'))">
    <meta property="og:description" content="@yield('meta_description', config('brand.seo.description'))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset(config('brand.logo')))">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', config('brand.seo.title'))">
    <meta name="twitter:description" content="@yield('meta_description', config('brand.seo.description'))">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <x-navigation />
    <main id="main">
        @php
            $flashes = [
                'success' => session('success'),
                'status' => session('status'),
                'error' => session('error'),
                'warning' => session('warning'),
                'info' => session('info'),
            ];
        @endphp
        @if (collect($flashes)->filter()->isNotEmpty())
            <div class="container pt-3">
                @foreach ($flashes as $type => $message)
                    @if ($message)
                        <x-alert :type="$type === 'status' ? 'success' : $type" dismissible>
                            {{ $message }}
                        </x-alert>
                    @endif
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>
    <x-site-footer />
    @stack('scripts')
</body>
</html>
