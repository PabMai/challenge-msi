<?php

test('el spinner se renderiza centrado, oculto y con su contenido', function () {
    $html = (string) $this->blade('<x-organism.spinner />');

    expect($html)->toContain('position-fixed')
        ->toContain('spinner-border')
        ->toContain('Procesando...')
        ->toContain('d-none');
});
