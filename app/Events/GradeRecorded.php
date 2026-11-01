<?php

namespace App\Events;

use App\Models\Grade;
use Illuminate\Foundation\Events\Dispatchable;

class GradeRecorded
{
    use Dispatchable;

    public function __construct(public readonly Grade $grade) {}
}
