<?php

namespace Tests\Feature\Academics;

use App\Models\SchoolYear;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ces règles sont garanties par PostgreSQL lui-même, pas seulement par PHP.
 * Chaque test ne contient qu'UNE requête fautive : PostgreSQL annule la transaction après une erreur.
 */
class SchoolYearConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_school_year_can_be_active(): void
    {
        SchoolYear::factory()->active()->create();

        $this->expectException(QueryException::class);

        SchoolYear::factory()->active()->create();
    }

    public function test_many_inactive_years_are_allowed(): void
    {
        SchoolYear::factory()->count(3)->create();
        SchoolYear::factory()->active()->create();

        $this->assertSame(1, SchoolYear::active()->count());
        $this->assertSame(4, SchoolYear::count());
        $this->assertNotNull(SchoolYear::current());
    }

    public function test_end_date_must_follow_start_date(): void
    {
        $this->expectException(QueryException::class);

        SchoolYear::factory()->create(['start_date' => '2027-07-31', 'end_date' => '2026-10-01']);
    }
}
