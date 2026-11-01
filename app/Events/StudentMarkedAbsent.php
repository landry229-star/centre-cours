<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Foundation\Events\Dispatchable;

/** Émis quand un élève passe à absent, jamais pour une simple re-sauvegarde. */
class StudentMarkedAbsent
{
    use Dispatchable;

    public function __construct(public readonly Attendance $attendance) {}
}
