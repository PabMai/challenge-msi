<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'location_id' => fn () => Location::factory(),
            // Nombre único por invocación: la tabla tiene
            // unique(location_id, name) y varios tests crean secciones
            // implícitas bajo una misma ubicación.
            'name' => fake()->unique()->lexify('Sección ????'),
        ];
    }
}
