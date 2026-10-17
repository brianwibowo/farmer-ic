<?php

namespace Database\Factories;

use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiAttachment> */
class AiAttachmentFactory extends Factory
{
    protected $model = AiAttachment::class;

    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'ai_message_id' => null,
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'ai/attachments/'.fake()->uuid().'.png',
            'original_name' => 'photo.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
            'kind' => 'image',
            'status' => 'ready',
            'metadata' => null,
        ];
    }
}
