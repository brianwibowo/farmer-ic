<?php

namespace App\Policies;

class AiKnowledgeDocumentPolicy extends ResourcePolicy
{
    protected string $resource = 'ai_knowledge_documents';

    protected ?string $teamPath = 'team';

    protected bool $ai = true;
}
