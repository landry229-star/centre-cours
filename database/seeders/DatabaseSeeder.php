<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(LevelSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->call([
            SchoolYearSeeder::class,
            RoomSeeder::class,
            FeeScheduleSeeder::class,
            UserSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSchoolDataSeeder::class);
        }
    }
}
