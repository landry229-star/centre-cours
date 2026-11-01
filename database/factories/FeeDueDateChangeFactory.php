<?php

namespace Database\Factories;

use App\Models\FeeDueDate;
use App\Models\FeeDueDateChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeDueDateChange>
 */
class FeeDueDateChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_due_date_id' => FeeDueDate::factory(),
            'old_due_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'new_due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'changed_by' => User::factory()->admin(),
        ];
    }
}
