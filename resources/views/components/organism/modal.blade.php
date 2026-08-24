<div {{ $attributes->merge(['tabindex' => '-1'])->class(['modal']) }}>
    <div class="modal-dialog modal-dialog-centered">
        {{ $slot }}
    </div>
</div>
