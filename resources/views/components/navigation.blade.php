<header class="site-header sticky-top">
    <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Primary">
        <div class="container">
            <x-brand-logo />
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primary-nav" aria-controls="primary-nav" aria-expanded="false" aria-label="Open menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primary-nav">
                <ul class="navbar-nav ms-lg-auto">
                    @foreach (config('brand.nav') as $item)
                        @php
                            $href = isset($item['route'])
                                ? route($item['route'])
                                : route('home').'#'.$item['fragment'];
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link" href="{{ $href }}">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                    @auth
                        <li class="nav-item">
                            <span class="nav-link">{{ auth()->user()->name }}</span>
                        </li>
                        <li class="nav-item">
                            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="nav-link" type="submit">Log out</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Log in</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>
</header>
