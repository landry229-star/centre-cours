<?php

namespace Tests\Feature\Billing;

use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

class FeeScheduleTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_sets_a_fee_and_spaces_in_the_amount_are_accepted(): void
    {
        $year = $this->billingYear();
        $level = Level::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.fee-schedules.store'), [
                'school_year_id' => $year->id, 'level_id' => $level->id, 'monthly_amount' => '15 000',
            ])
            ->assertRedirect(route('admin.fee-schedules.index', ['school_year_id' => $year->id]));

        $this->assertDatabaseHas('fee_schedules', ['school_year_id' => $year->id, 'level_id' => $level->id, 'monthly_amount' => 15000]);
    }

    public function test_setting_the_fee_again_updates_it_without_duplicate(): void
    {
        $year = $this->billingYear();
        $level = Level::factory()->create();
        $admin = $this->admin();
        $payload = ['school_year_id' => $year->id, 'level_id' => $level->id];

        $this->actingAs($admin)->post(route('admin.fee-schedules.store'), $payload + ['monthly_amount' => 10000]);
        $this->actingAs($admin)->post(route('admin.fee-schedules.store'), $payload + ['monthly_amount' => 12000]);

        $this->assertSame(1, FeeSchedule::count());
        $this->assertSame(12000, FeeSchedule::first()->monthly_amount);
    }

    public function test_amount_is_validated(): void
    {
        $year = $this->billingYear();
        $level = Level::factory()->create();

        foreach (['0', '-5', 'abc', '99999999999'] as $bad) {
            $this->actingAs($this->admin())
                ->post(route('admin.fee-schedules.store'), ['school_year_id' => $year->id, 'level_id' => $level->id, 'monthly_amount' => $bad])
                ->assertSessionHasErrors('monthly_amount');
        }

        $this->assertSame(0, FeeSchedule::count());
    }

    public function test_fee_is_locked_once_a_payment_exists(): void
    {
        $this->travelTo('2031-01-15');
        $enrollment = $this->billingEnrollment(fee: 15000);
        $fee = FeeSchedule::firstOrFail();
        $this->pay($enrollment, '2030-10-01', 5000);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.fee-schedules.store'), [
                'school_year_id' => $fee->school_year_id, 'level_id' => $fee->level_id, 'monthly_amount' => 20000,
            ])
            ->assertSessionHas('error');
        $this->assertSame(15000, $fee->fresh()->monthly_amount);

        $this->actingAs($admin)->delete(route('admin.fee-schedules.destroy', $fee))->assertSessionHas('error');
        $this->assertModelExists($fee);
    }

    public function test_unused_fee_can_be_deleted(): void
    {
        $fee = FeeSchedule::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.fee-schedules.destroy', $fee))
            ->assertRedirect();

        $this->assertModelMissing($fee);
    }

    public function test_only_admin_can_manage_fees(): void
    {
        $fee = FeeSchedule::factory()->create();

        foreach ([User::factory()->teacher()->create(), User::factory()->guardian()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.fee-schedules.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.fee-schedules.store'), [
                'school_year_id' => $fee->school_year_id, 'level_id' => $fee->level_id, 'monthly_amount' => 1,
            ])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.fee-schedules.destroy', $fee))->assertForbidden();
        }
    }

    public function test_page_renders(): void
    {
        $this->billingEnrollment();

        $this->actingAs($this->admin())->get(route('admin.fee-schedules.index'))->assertOk();
    }

    public function test_database_allows_one_fee_per_level_and_year(): void
    {
        $fee = FeeSchedule::factory()->create();

        $this->expectException(QueryException::class);
        FeeSchedule::factory()->create(['school_year_id' => $fee->school_year_id, 'level_id' => $fee->level_id]);
    }

    public function test_database_rejects_a_zero_fee(): void
    {
        $this->expectException(QueryException::class);
        FeeSchedule::factory()->create(['monthly_amount' => 0]);
    }
}
