<button {{ $attributes->merge(['type' => $type])->class($classes()) }}>
    {{ $slot }}
</button>
