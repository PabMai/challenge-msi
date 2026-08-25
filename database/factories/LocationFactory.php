<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Nombre único por invocación: locations.name es UNIQUE y varios
            // factories anidan ubicaciones implícitas en un mismo test.
            'name' => fake()->unique()->lexify('Ubicación ????'),
            'sort_order' => 1,
        ];
    }
}
