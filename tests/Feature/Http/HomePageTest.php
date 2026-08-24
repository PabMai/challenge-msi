<?php

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET / renderiza la pagina principal con el formulario de reserva', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Formulario de reserva')
        ->assertSee('Fecha de reserva')
        ->assertSee('Cantidad de personas')
        ->assertSee('Seleccionar ubicación');
});

test('GET / lista las ubicaciones disponibles ordenadas', function () {
    Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Seleccionar ubicación', 'Salón', 'Terraza']);
});
