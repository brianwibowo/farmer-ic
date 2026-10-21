<?php

namespace App\AI\Services;

use App\Models\AiKnowledgeDocument;
use App\Models\Team;
use RuntimeException;

class KnowledgeService
{
    private readonly DemoDataKnowledgeSource $demoData;

    public function __construct(?DemoDataKnowledgeSource $demoData = null)
    {
        $this->demoData = $demoData ?? new DemoDataKnowledgeSource;
    }

    /** @return array{facts: string, policy: string, adaptive: string, hashes: array{facts: string, policy: string, adaptive: array<int, string>}} */
    public function snapshot(?Team $team = null): array
    {
        $facts = $this->read('facts_path');
        $policy = $this->read('policy_path');
        $published = $team?->aiKnowledgeDocuments()
            ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
            ->orderBy('id')
            ->get(['id', 'title', 'content', 'content_hash']) ?? collect();
        $adaptive = $published
            ->map(fn (AiKnowledgeDocument $document): string => "## {$document->title}\n{$document->content}")
            ->implode("\n\n");

        return [
            'facts' => $facts,
            'policy' => $policy,
            'adaptive' => $adaptive,
            'hashes' => [
                'facts' => hash('sha256', $facts),
                'policy' => hash('sha256', $policy),
                'adaptive' => $published->pluck('content_hash')->values()->all(),
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     id: string|int,
     *     title: string,
     *     content: string,
     *     source: string,
     *     version: int|string|null
     * }>
     */
    public function retrievalDocuments(?Team $team = null): array
    {
        $documents = [
            [
                'id' => 'application-facts',
                'title' => 'Fakta aplikasi Farmer IC',
                'content' => $this->read('facts_path'),
                'source' => 'Knowledge base Farmer IC · app facts',
                'version' => 'static-v1',
            ],
            [
                'id' => 'assistant-policy',
                'title' => 'Kebijakan Asisten Kebun',
                'content' => $this->read('policy_path'),
                'source' => 'Knowledge base Farmer IC · assistant policy',
                'version' => 'static-v1',
            ],
        ];

        $demoGuides = $this->readOptional('demo_guides_path');
        if ($demoGuides !== null) {
            $documents[] = [
                'id' => 'demo-agronomy-guides',
                'title' => 'Panduan agronomi demo Farmer IC',
                'content' => $demoGuides,
                'source' => 'Knowledge base Farmer IC · demo agronomy guide',
                'version' => 'demo-v1',
            ];
        }

        $published = $team?->aiKnowledgeDocuments()
            ->where('status', AiKnowledgeDocument::STATUS_PUBLISHED)
            ->orderBy('id')
            ->get(['id', 'title', 'content', 'source', 'version']) ?? collect();

        foreach ($published as $document) {
            $documents[] = [
                'id' => $document->id,
                'title' => $document->title,
                'content' => $document->content,
                'source' => $document->source ?: 'Knowledge team Farmer IC',
                'version' => $document->version,
            ];
        }

        foreach ($this->demoData->documents() as $document) {
            $documents[] = [
                'id' => $document['id'],
                'title' => $document['title'],
                'content' => $document['content'],
                'source' => 'Knowledge base Farmer IC · farmDemoData.json origin/dev',
                'version' => 'origin-dev-v2',
            ];
        }

        return $documents;
    }

    protected function read(string $key): string
    {
        $path = config("ai.knowledge.{$key}");

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Knowledge base file [{$key}] is unavailable.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Knowledge base file [{$key}] could not be read.");
        }

        return trim($contents);
    }

    protected function readOptional(string $key): ?string
    {
        $path = config("ai.knowledge.{$key}");

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : trim($contents);
    }
}
