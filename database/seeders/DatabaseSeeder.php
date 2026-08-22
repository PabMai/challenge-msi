<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //$this->seedUsers();
        $this->seedLocations();
        $this->seedTables();
    }

    private function seedUsers(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    private function seedLocations(): void
    {
        $locations = ['Salón', 'Terraza'];

        foreach ($locations as $index => $name) {
            Location::create([
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function seedTables(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Table::factory()->create([
                'location_id' => 1,
                'code' => sprintf('S%02d', $i),
                'capacity' => $i % 2 === 1 ? 2 : 4,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Table::factory()->create([
                'location_id' => 2,
                'code' => sprintf('T%02d', $i),
                'capacity' => $i % 2 === 1 ? 4 : 8,
            ]);
        }
    }
}
