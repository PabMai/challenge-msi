<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            // La sección es obligatoria: si solo llega la ubicación se crea
            // una sección nueva dentro de ella; si solo llega la sección, la
            // ubicación se deriva de ella.
            'location_id' => function (array $attributes) {
                if (isset($attributes['section_id'])) {
                    return Section::query()->findOrFail($attributes['section_id'])->location_id;
                }

                return Location::factory();
            },
            'section_id' => function (array $attributes) {
                if (! isset($attributes['section_id']) && isset($attributes['location_id'])) {
                    return Section::factory()->create([
                        'location_id' => $attributes['location_id'],
                    ])->id;
                }

                return Section::factory();
            },
            'code' => 'S01',
            'capacity' => 2,
        ];
    }
}
