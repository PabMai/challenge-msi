<label {{ $attributes->merge(['for' => $for])->class(['col-form-label']) }}>
    {{ $slot }}
</label>
