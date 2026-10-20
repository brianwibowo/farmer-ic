<?php

namespace App\AI\Services;

/**
 * Split knowledge document content into retrieval-sized chunks.
 *
 * Chunking priority (v1):
 *   1. Markdown heading boundary
 *   2. Paragraph (double newline)
 *   3. Sentence boundary
 *   4. Character budget fallback
 *
 * Heading context is preserved: each chunk carries the nearest heading path
 * so retrieval results can include the section they came from.
 */
class KnowledgeChunker
{
    /**
     * @return array<int, array{id: string, title: string, source: string, content: string, heading: string}>
     */
    public function chunk(string $content, string $title, string $source, string $documentId): array
    {
        $maxChars = (int) config('ai.rag.chunk_chars', 1800);
        $overlapChars = (int) config('ai.rag.chunk_overlap_chars', 200);

        $sections = $this->splitBySections($content);
        $chunks = [];
        $index = 0;
        $previousTail = '';

        foreach ($sections as $section) {
            $heading = $section['heading'];
            $blocks = $this->splitByParagraphs($section['body']);

            $buffer = '';

            foreach ($blocks as $block) {
                $block = trim($block);
                if ($block === '') {
                    continue;
                }

                if ($buffer !== '' && mb_strlen($buffer."\n\n".$block) > $maxChars) {
                    $chunkContent = $this->applyOverlap($previousTail, $buffer);
                    $chunks[] = $this->makeChunk($chunkContent, $title, $source, $documentId, $index++, $heading);
                    $previousTail = $this->tail($buffer, $overlapChars);
                    $buffer = '';
                }

                if (mb_strlen($block) > $maxChars) {
                    if ($buffer !== '') {
                        $chunkContent = $this->applyOverlap($previousTail, $buffer);
                        $chunks[] = $this->makeChunk($chunkContent, $title, $source, $documentId, $index++, $heading);
                        $previousTail = $this->tail($buffer, $overlapChars);
                        $buffer = '';
                    }

                    foreach ($this->splitBySentences($block, $maxChars) as $sentence) {
                        $chunkContent = $this->applyOverlap($previousTail, $sentence);
                        $chunks[] = $this->makeChunk($chunkContent, $title, $source, $documentId, $index++, $heading);
                        $previousTail = $this->tail($sentence, $overlapChars);
                    }

                    continue;
                }

                $buffer = $buffer === '' ? $block : $buffer."\n\n".$block;
            }

            if ($buffer !== '') {
                $chunkContent = $this->applyOverlap($previousTail, $buffer);
                $chunks[] = $this->makeChunk($chunkContent, $title, $source, $documentId, $index++, $heading);
                $previousTail = $this->tail($buffer, $overlapChars);
            }
        }

        return $chunks;
    }

    /**
     * Split content by markdown heading boundaries.
     *
     * @return array<int, array{heading: string, body: string}>
     */
    private function splitBySections(string $content): array
    {
        $lines = explode("\n", trim($content));
        $sections = [];
        $currentHeading = '';
        $currentBody = '';

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $matches)) {
                if ($currentBody !== '' || $currentHeading !== '') {
                    $sections[] = ['heading' => $currentHeading, 'body' => trim($currentBody)];
                }
                $currentHeading = trim($matches[2]);
                $currentBody = '';
            } else {
                $currentBody .= $line."\n";
            }
        }

        if ($currentBody !== '' || $currentHeading !== '') {
            $sections[] = ['heading' => $currentHeading, 'body' => trim($currentBody)];
        }

        if ($sections === []) {
            $sections[] = ['heading' => '', 'body' => trim($content)];
        }

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    private function splitByParagraphs(string $text): array
    {
        $blocks = preg_split('/\n\s*\n/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $blocks), fn (string $block): bool => $block !== ''));
    }

    /**
     * Break a very long block by sentence boundaries so no chunk exceeds the limit.
     *
     * @return array<int, string>
     */
    private function splitBySentences(string $text, int $maxChars): array
    {
        $sentences = preg_split('/(?<=[.!?。])\s+/u', $text) ?: [$text];
        $result = [];
        $buffer = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if ($buffer !== '' && mb_strlen($buffer.' '.$sentence) > $maxChars) {
                $result[] = $buffer;
                $buffer = '';
            }

            $buffer = $buffer === '' ? $sentence : $buffer.' '.$sentence;
        }

        if ($buffer !== '') {
            $result[] = $buffer;
        }

        return $result;
    }

    private function applyOverlap(string $previousTail, string $content): string
    {
        if ($previousTail === '') {
            return $content;
        }

        return $previousTail."\n\n".$content;
    }

    private function tail(string $text, int $chars): string
    {
        if ($chars <= 0 || mb_strlen($text) <= $chars) {
            return '';
        }

        $tail = mb_substr($text, -$chars);
        $sentenceBoundary = preg_match('/^.*?[.!?。]\s/su', $tail, $matches);

        if ($sentenceBoundary && mb_strlen($matches[0]) < mb_strlen($tail)) {
            return mb_substr($tail, mb_strlen($matches[0]));
        }

        return $tail;
    }

    /** @return array{id: string, title: string, source: string, content: string, heading: string} */
    private function makeChunk(string $content, string $title, string $source, string $documentId, int $index, string $heading): array
    {
        return [
            'id' => $documentId.':chunk:'.$index,
            'title' => $heading !== '' ? $heading : $title,
            'source' => $source,
            'content' => $content,
            'heading' => $heading,
        ];
    }
}
