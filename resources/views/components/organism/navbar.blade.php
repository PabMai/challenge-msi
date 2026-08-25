<nav {{ $attributes->class(['navbar', 'bg-dark']) }}>
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1 text-white">{{ $brand }}</span>
        @isset($links)
            <ul class="navbar-nav ms-auto flex-row gap-2 mb-0">
                {{ $links }}
            </ul>
        @endisset
    </div>
</nav>
