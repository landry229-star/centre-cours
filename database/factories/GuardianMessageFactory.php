<?php

namespace Database\Factories;

use App\Models\GuardianConversation;
use App\Models\GuardianMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianMessage>
 */
class GuardianMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_conversation_id' => GuardianConversation::factory(),
            'sender_id' => User::factory()->admin(),
            'body' => fake()->paragraph(),
            'read_at' => null,
        ];
    }
}
