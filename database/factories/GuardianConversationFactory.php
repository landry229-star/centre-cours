<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\GuardianConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianConversation>
 */
class GuardianConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_id' => Guardian::factory(),
            'last_message_at' => now(),
        ];
    }
}
