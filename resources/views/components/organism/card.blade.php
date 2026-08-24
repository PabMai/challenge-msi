<div {{ $attributes->class(['card']) }}>
    @if ($header)
        <div class="card-header">{{ $header }}</div>
    @endif
    {{ $slot }}
</div>
