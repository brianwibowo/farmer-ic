<?php

namespace App\Http\Controllers\AI;

use App\AI\Services\AiChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreAiConversationRequest;
use App\Models\AiConversation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiConversationController extends Controller
{
    public function __construct(private readonly AiChatService $chat) {}

    public function index(Request $request, Team $current_team): JsonResponse
    {
        Gate::authorize('viewAny', [AiConversation::class, $current_team]);
        $actor = $request->user();
        $conversations = AiConversation::visibleTo($actor, $current_team)
            ->where('user_id', $actor->id)
            ->with(['messages' => fn ($query) => $query->visibleTo($actor, $current_team)->latest('id')->limit(1)])
            ->latest('updated_at')
            ->paginate(30)
            ->through(fn (AiConversation $conversation): array => $this->conversationPayload($conversation));

        return response()->json($conversations);
    }

    public function store(StoreAiConversationRequest $request, Team $current_team): JsonResponse
    {
        Gate::authorize('create', [AiConversation::class, $current_team]);
        $conversation = $this->chat->createConversation(
            $request->user(),
            $current_team,
            $request->validated('title'),
        );

        return response()->json($this->conversationPayload($conversation), 201);
    }

    public function show(Request $request, Team $current_team, AiConversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->ownedConversation($actor, $current_team, $conversation);
        $conversation->setRelation('team', $current_team);
        $conversation->load(['messages' => fn ($messages) => $messages->visibleTo($actor, $current_team)
            ->orderBy('id')->with(['attachments' => fn ($attachments) => $attachments->visibleTo($actor, $current_team)])]);

        return response()->json([
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $conversation->messages->map(fn ($message): array => $this->chat->messagePayload($message->setRelation('conversation', $conversation)))->values(),
        ]);
    }

    public function ownedConversation(User $actor, Team $team, AiConversation $conversation): AiConversation
    {
        Gate::authorize('view', $conversation);
        abort_unless(
            $conversation->team_id === $team->id && $conversation->user_id === $actor->id,
            404,
        );

        return AiConversation::visibleTo($actor, $team)->whereKey($conversation->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    protected function conversationPayload(AiConversation $conversation): array
    {
        $lastMessage = $conversation->relationLoaded('messages') ? $conversation->messages->first() : null;

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
