<?php

namespace App\Http\Controllers\AI;

use App\AI\Services\AiChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\StreamedEvent;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiMessageController extends Controller
{
    public function __construct(
        private readonly AiChatService $chat,
        private readonly AiConversationController $conversations,
    ) {}

    public function store(
        StoreAiMessageRequest $request,
        Team $current_team,
        ?AiConversation $conversation = null,
    ): JsonResponse|StreamedResponse {
        if ($conversation !== null) {
            $conversation = $this->conversations->ownedConversation($current_team, $conversation);
        } elseif ($request->filled('conversation_id')) {
            $conversation = AiConversation::query()
                ->whereKey($request->integer('conversation_id'))
                ->where('team_id', $current_team->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        } else {
            $conversation = $this->chat->createConversation($request->user(), $current_team);
        }

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);
        $this->chat->createUserMessage($conversation, $request->user(), $request->input('message'), $files);

        $events = $this->chat->streamReply($conversation->fresh());

        return response()->eventStream(
            function () use ($events): iterable {
                foreach ($events as $event) {
                    yield new StreamedEvent($event['event'], $event['data']);
                }
            },
            [
                'Cache-Control' => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
            ],
            null,
        );
    }
}
