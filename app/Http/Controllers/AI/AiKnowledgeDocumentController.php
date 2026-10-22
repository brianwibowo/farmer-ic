<?php

namespace App\Http\Controllers\AI;

use App\AI\Jobs\IndexKnowledgeDocument;
use App\AI\Jobs\RemoveKnowledgeDocumentFromIndex;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreAiKnowledgeDocumentRequest;
use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiKnowledgeDocumentController extends Controller
{
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $this->authorizeManager($request, $current_team);

        return response()->json([
            'data' => $current_team->aiKnowledgeDocuments()
                ->latest('updated_at')
                ->get()
                ->map(fn (AiKnowledgeDocument $document): array => $this->payload($document))
                ->values(),
        ]);
    }

    public function store(StoreAiKnowledgeDocumentRequest $request, Team $current_team): JsonResponse
    {
        $this->authorizeManager($request, $current_team);
        $content = trim((string) $request->validated('content'));

        $document = $current_team->aiKnowledgeDocuments()->create([
            'created_by' => $request->user()->id,
            'title' => trim((string) $request->validated('title')),
            'content' => $content,
            'status' => AiKnowledgeDocument::STATUS_DRAFT,
            'source' => $request->validated('source'),
            'content_hash' => hash('sha256', $content),
            'version' => 1,
        ]);

        return response()->json($this->payload($document), 201);
    }

    public function update(StoreAiKnowledgeDocumentRequest $request, Team $current_team, AiKnowledgeDocument $document): JsonResponse
    {
        $this->authorizeManager($request, $current_team);
        abort_unless($document->team_id === $current_team->id, 404);

        $content = trim((string) $request->validated('content'));
        $document->forceFill([
            'title' => trim((string) $request->validated('title')),
            'content' => $content,
            'source' => $request->validated('source'),
            'status' => AiKnowledgeDocument::STATUS_DRAFT,
            'reviewed_by' => null,
            'published_at' => null,
            'content_hash' => hash('sha256', $content),
            'version' => $document->version + 1,
        ])->save();

        $this->dispatchIndexRemoval($document);

        return response()->json($this->payload($document->fresh()));
    }

    public function publish(Request $request, Team $current_team, AiKnowledgeDocument $document): JsonResponse
    {
        $this->authorizeManager($request, $current_team);
        abort_unless($document->team_id === $current_team->id, 404);

        $document->forceFill([
            'status' => AiKnowledgeDocument::STATUS_PUBLISHED,
            'reviewed_by' => $request->user()->id,
            'published_at' => now(),
        ])->save();

        if (config('ai.rag.vector_enabled')) {
            IndexKnowledgeDocument::dispatch($document->id);
        }

        return response()->json($this->payload($document->fresh()));
    }

    public function archive(Request $request, Team $current_team, AiKnowledgeDocument $document): JsonResponse
    {
        $this->authorizeManager($request, $current_team);
        abort_unless($document->team_id === $current_team->id, 404);

        $document->forceFill([
            'status' => AiKnowledgeDocument::STATUS_ARCHIVED,
            'reviewed_by' => $request->user()->id,
            'published_at' => null,
        ])->save();

        $this->dispatchIndexRemoval($document);

        return response()->json($this->payload($document->fresh()));
    }

    public function destroy(Request $request, Team $current_team, AiKnowledgeDocument $document): JsonResponse
    {
        $this->authorizeManager($request, $current_team);
        abort_unless($document->team_id === $current_team->id, 404);

        // Capture the scoped identity before the database cascade removes the
        // chunk rows; the queued cleanup can then use payload filtering even
        // when no chunk point ids remain.
        $documentId = (int) $document->getKey();
        $teamId = (int) $current_team->getKey();
        $document->delete();

        if (config('ai.rag.vector_enabled')) {
            RemoveKnowledgeDocumentFromIndex::dispatch($documentId, $teamId);
        }

        return response()->json(['deleted' => true]);
    }

    protected function authorizeManager(Request $request, Team $team): void
    {
        abort_unless($request->user()?->hasTeamPermission($team, TeamPermission::ManageKnowledge), 403);
    }

    private function dispatchIndexRemoval(AiKnowledgeDocument $document): void
    {
        if (config('ai.rag.vector_enabled')) {
            RemoveKnowledgeDocumentFromIndex::dispatch((int) $document->getKey(), (int) $document->team_id);
        }
    }

    /** @return array<string, mixed> */
    protected function payload(AiKnowledgeDocument $document): array
    {
        return [
            'id' => (string) $document->id,
            'title' => $document->title,
            'content' => $document->content,
            'status' => $document->status,
            'source' => $document->source,
            'content_hash' => $document->content_hash,
            'version' => $document->version,
            'published_at' => $document->published_at?->toISOString(),
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
        ];
    }
}
