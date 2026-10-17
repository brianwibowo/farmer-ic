<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiConversation> */
class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => 'active',
            'knowledge_snapshot' => null,
            'last_message_at' => null,
        ];
    }
}
