<?php

use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not seed fictional operating data in production', function () {
    $this->app['env'] = 'production';

    app(DatabaseSeeder::class)->run();

    expect(Level::query()->count())->toBe(13)
        ->and(SchoolYear::query()->count())->toBe(0)
        ->and(Room::query()->count())->toBe(0)
        ->and(FeeSchedule::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});
