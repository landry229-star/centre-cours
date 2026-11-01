<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Salle 1', 'Salle 2', 'Salle 3', 'Salle 4', 'Salle informatique'] as $name) {
            Room::firstOrCreate(['name' => $name], ['capacity' => 40]);
        }
    }
}
