<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Foundation\Events\Dispatchable;

class ScheduleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Schedule $schedule,
        public readonly string $change,
    ) {}
}
