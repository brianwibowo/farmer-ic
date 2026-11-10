<?php

namespace App\Http\Controllers\AI;

use App\AI\Services\AiChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreAiConversationRequest;
use App\Models\AiConversation;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class AiConversationController extends Controller
{
    public function __construct(private readonly AiChatService $chat) {}

    public function index(Team $current_team): JsonResponse
    {
        $conversations = AiConversation::query()
            ->where('team_id', $current_team->id)
            ->where('user_id', request()->user()->id)
            ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->latest('updated_at')
            ->paginate(30)
            ->through(fn (AiConversation $conversation): array => $this->conversationPayload($conversation));

        return response()->json($conversations);
    }

    public function store(StoreAiConversationRequest $request, Team $current_team): JsonResponse
    {
        $conversation = $this->chat->createConversation(
            $request->user(),
            $current_team,
            $request->validated('title'),
        );

        return response()->json($this->conversationPayload($conversation), 201);
    }

    public function show(Team $current_team, AiConversation $conversation): JsonResponse
    {
        $conversation = $this->ownedConversation($current_team, $conversation);
        $conversation->load(['messages.attachments']);

        return response()->json([
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $conversation->messages->map(fn ($message): array => $this->chat->messagePayload($message))->values(),
        ]);
    }

    public function ownedConversation(Team $team, AiConversation $conversation): AiConversation
    {
        abort_unless(
            $conversation->team_id === $team->id && $conversation->user_id === request()->user()->id,
            404,
        );

        return $conversation;
    }

    /** @return array<string, mixed> */
    protected function conversationPayload(AiConversation $conversation): array
    {
        $lastMessage = $conversation->messages->first();

        return [
            'id' => (string) $conversation->id,
            'title' => $conversation->title ?: 'Percakapan baru',
            'status' => $conversation->status,
            'preview' => $lastMessage?->content,
            'updated_at' => $conversation->updated_at?->toISOString(),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
        ];
    }
}
