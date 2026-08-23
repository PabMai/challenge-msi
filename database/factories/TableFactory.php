<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'location_id' => fn () => Location::factory(),
            'code' => 'S01',
            'capacity' => 2,
        ];
    }
}
