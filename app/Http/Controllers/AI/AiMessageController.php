<?php

namespace App\Http\Controllers\AI;

use App\AI\Services\AiChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreAiMessageRequest;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\StreamedEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);
        [$conversation, $userMessage] = DB::transaction(function () use ($request, $current_team, $conversation, $files): array {
            $actor = $request->user();
            if ($conversation !== null) {
                $conversation = $this->conversations->ownedConversation($actor, $current_team, $conversation);
            } elseif ($request->filled('conversation_id')) {
                Gate::authorize('viewAny', [AiConversation::class, $current_team]);
                $conversation = AiConversation::visibleTo($actor, $current_team)
                    ->whereKey($request->input('conversation_id'))->where('user_id', $actor->id)->firstOrFail();
                Gate::authorize('view', $conversation);
            } else {
                Gate::authorize('create', [AiConversation::class, $current_team]);
                $conversation = $this->chat->createConversation($actor, $current_team);
            }
            Gate::authorize('create', [AiMessage::class, $conversation]);
            if ($files !== []) {
                Gate::authorize('create', [AiAttachment::class, $conversation]);
            }

            return [$conversation, $this->chat->createUserMessage($conversation, $actor, $request->input('message'), $files)];
        });

        $events = $this->chat->streamReply(
            $conversation->fresh(),
            $request->boolean('is_infographic'),
            $userMessage,
        );

        return response()->eventStream(
            function () use ($events): iterable {
                $executionTimeout = (int) config('ai.openrouter.execution_timeout', 120);
                if ($executionTimeout > 0) {
                    set_time_limit($executionTimeout);
                }

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
