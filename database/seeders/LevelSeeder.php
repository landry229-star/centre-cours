<?php

namespace Database\Seeders;

use App\Enums\Cycle;
use App\Models\Level;
use Illuminate\Database\Seeder;

/** Niveaux du système éducatif béninois (données de référence, sans danger en production). */
class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['CI', Cycle::Primary], ['CP', Cycle::Primary], ['CE1', Cycle::Primary],
            ['CE2', Cycle::Primary], ['CM1', Cycle::Primary], ['CM2', Cycle::Primary],
            ['6ème', Cycle::College], ['5ème', Cycle::College], ['4ème', Cycle::College], ['3ème', Cycle::College],
            ['Seconde', Cycle::Lycee], ['Première', Cycle::Lycee], ['Terminale', Cycle::Lycee],
        ];

        foreach ($levels as $index => [$name, $cycle]) {
            Level::updateOrCreate(
                ['name' => $name],
                ['cycle' => $cycle, 'position' => $index + 1],
            );
        }
    }
}
