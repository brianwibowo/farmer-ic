<?php

namespace App\Policies;

use App\Models\AiKnowledgeDocument;

class AiKnowledgeChunkPolicy extends ResourcePolicy
{
    protected string $resource = 'ai_knowledge_chunks';

    protected ?string $teamPath = 'document.team';

    protected bool $ai = true;

    protected array $parents = [AiKnowledgeDocument::class];
}
