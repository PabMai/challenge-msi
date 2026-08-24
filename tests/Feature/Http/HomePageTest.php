<?php

test('GET / renderiza la pagina principal con el formulario de reserva', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Formulario de reserva')
        ->assertSee('Fecha de reserva')
        ->assertSee('Cantidad de personas');
});
