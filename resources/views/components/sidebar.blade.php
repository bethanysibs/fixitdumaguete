@props([
    'label' => 'Sidebar',
    'items' => [],
])

<aside {{ $attributes->merge(['class' => 'app-sidebar']) }} aria-label="{{ $label }}">
    @if (count($items))
        <ul class="nav flex-column">
            @foreach ($items as $item)
                <li class="nav-item">
                    <a
                        class="nav-link {{ ! empty($item['active']) ? 'active' : '' }}"
                        href="{{ $item['url'] }}"
                        @if (! empty($item['active'])) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif
    {{ $slot }}
</aside>
