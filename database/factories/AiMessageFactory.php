<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiMessage> */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'user_id' => null,
            'role' => 'user',
            'content' => fake()->sentence(),
            'status' => 'completed',
            'provider' => null,
            'model' => null,
            'request_id' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'latency_ms' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (): array => [
            'role' => 'assistant',
            'user_id' => null,
        ]);
    }
}
