<?php

use App\Models\Location;
use App\Models\Section;
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

test('GET / lista las secciones agrupadas por ubicacion', function () {
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    $terraza = Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);

    Section::query()->create(['location_id' => $salon->id, 'name' => 'Bar']);
    Section::query()->create(['location_id' => $salon->id, 'name' => 'Salón Principal']);
    Section::query()->create(['location_id' => $terraza->id, 'name' => 'Jardín']);
    Section::query()->create(['location_id' => $terraza->id, 'name' => 'Área Infantil']);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            'Salón', 'Terraza',
            'Bar', 'Salón Principal', 'Jardín', 'Área Infantil',
        ]);
});
