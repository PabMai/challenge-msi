<select {{ $attributes->merge($attrs())->class($classes()) }}>
    {{ $slot }}
</select>
