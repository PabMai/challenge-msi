<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Section;
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
        $salon = Location::create(['name' => 'Salón', 'sort_order' => 1]);
        $terraza = Location::create(['name' => 'Terraza', 'sort_order' => 2]);

        Section::create(['location_id' => $salon->id, 'name' => 'Bar']);
        Section::create(['location_id' => $salon->id, 'name' => 'Salón Principal']);
        Section::create(['location_id' => $terraza->id, 'name' => 'Jardín']);
        Section::create(['location_id' => $terraza->id, 'name' => 'Área Infantil']);
    }

    private function seedTables(): void
    {
        // Salón: S01-S03 en Bar, S04-S10 en Salón Principal (secciones 1 y 2)
        for ($i = 1; $i <= 10; $i++) {
            Table::factory()->create([
                'location_id' => 1,
                'section_id' => $i <= 3 ? 1 : 2,
                'code' => sprintf('S%02d', $i),
                'capacity' => $i % 2 === 1 ? 2 : 4,
            ]);
        }

        // Terraza: T01-T03 en Jardín, T04-T05 en Área Infantil (secciones 3 y 4)
        for ($i = 1; $i <= 5; $i++) {
            Table::factory()->create([
                'location_id' => 2,
                'section_id' => $i <= 3 ? 3 : 4,
                'code' => sprintf('T%02d', $i),
                'capacity' => $i % 2 === 1 ? 4 : 8,
            ]);
        }
    }
}
