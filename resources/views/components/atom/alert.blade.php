<div {{ $attributes->merge(['role' => 'alert'])->class($classes()) }}>
    {{ $slot }}
</div>
