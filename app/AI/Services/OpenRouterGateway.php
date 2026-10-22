<?php

namespace App\AI\Services;

use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\LlmException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouterGateway implements LlmGateway
{
    public function complete(array $messages, string $model, array $options = []): array
    {
        if (($options['image_generation'] ?? false) === true) {
            return $this->completeImage($messages, $model, $options);
        }

        $response = $this->send(
            $this->request(),
            '/chat/completions',
            $this->payload($messages, $model, $options),
        );

        return $this->decodeResponse($response);
    }

    /**
     * OpenRouter image models use the Images API, not chat/completions.
     * Normalize its b64_json/url response to the shape consumed by AiChatService.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function completeImage(array $messages, string $model, array $options): array
    {
        $userText = '';
        $recentContext = '';
        $systemText = '';
        foreach (array_reverse($messages) as $message) {
            $role = $message['role'] ?? null;
            $content = $message['content'] ?? '';
            // Image-generation prompts are text-only. A multimodal history
            // message can contain a data URI under image_url; JSON-encoding
            // the whole content would copy megabytes of base64 bytes into the
            // provider prompt. Keep only explicit text parts.
            $text = $this->textContent($content);
            if ($role === 'system' && $systemText === '') {
                $systemText = $text;
            } elseif ($role === 'user' && $userText === '') {
                $userText = trim($text);
            } elseif ($role === 'assistant' && $recentContext === '' && trim($text) !== '') {
                $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
                $recentContext = mb_substr($clean, 0, 250);
            }
        }

        $basePrompt = 'A professional agricultural image or illustration for the Farmer IC smart farming platform. ';
        $contextPart = $recentContext !== '' ? 'Context: '.$recentContext.'. ' : '';
        $verifiedData = trim((string) ($options['image_prompt_context'] ?? ''));
        // PromptAssembler puts retrieved records in the system message. The
        // Images API only accepts a flat prompt, so carry that evidence over
        // explicitly. Without this bridge the image model sees the user's
        // topic but none of the canonical farm fields and fills the gaps with
        // invented acreage, crops, or locations.
        if ($verifiedData === '') {
            $verifiedData = $this->retrievedEvidenceContext($systemText);
        }
        $verifiedDataPart = $verifiedData !== ''
            ? 'Verified data instructions (reference only): '.$verifiedData.' '
            : '';
        $userPart = 'Topic: '.$userText.'. ';
        $styleDirectives = 'Style: Match the requested visual form. Use a clean, readable composition and relevant crops or fields when appropriate, with high contrast and a restrained professional palette. Use distinct nodes or a process flow only when the request asks for a diagram or flowchart. Keep labels short and legible. Use only verified values supplied above; never invent or alter a name, location, crop, acreage, date, sensor, technology, weather value, or other number. If a field is absent, omit it. No random decorative elements and no calendar unless explicitly requested.';

        $prompt = trim($basePrompt.$verifiedDataPart.$contextPart.$userPart.$styleDirectives);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => (int) ($options['n'] ?? 1),
            'size' => (string) ($options['size'] ?? '1024x1024'),
        ];
        $response = $this->send($this->request(), '/images', $payload);
        $decoded = $this->decodeResponse($response);
        $images = [];
        foreach ((array) ($decoded['data'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = $item['url'] ?? null;
            if (is_string($item['b64_json'] ?? null)) {
                $mediaType = (string) ($item['media_type'] ?? 'image/png');
                if (! preg_match('/^image\/[a-z0-9.+-]+$/i', $mediaType)) {
                    $mediaType = 'image/png';
                }
                $url = 'data:'.$mediaType.';base64,'.$item['b64_json'];
            }
            if (is_string($url) && $url !== '') {
                $images[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
            }
        }

        return [
            'id' => $decoded['id'] ?? null,
            'provider_request_id' => $response->header('x-request-id'),
            'choices' => [['message' => [
                'content' => '',
                'images' => $images,
            ]]],
            'usage' => $decoded['usage'] ?? [],
        ];
    }

    /**
     * Extract the bounded evidence block from PromptAssembler's system
     * message for providers that expose only a single image prompt.
     *
     * The evidence is reference data, never provider instructions. Keep the
     * original content intact enough for canonical JSON fields (for example,
     * kebun-002's 180/750 ha values) to survive, but cap the prompt size.
     */
    protected function retrievedEvidenceContext(string $systemText): string
    {
        if ($systemText === '' || ! preg_match('/<retrieved-evidence-json>\s*(.*?)\s*<\/retrieved-evidence-json>/su', $systemText, $match)) {
            return '';
        }

        $decoded = json_decode(trim((string) $match[1]), true);
        if (! is_array($decoded)) {
            return '';
        }

        $records = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $record = [
                'title' => trim((string) ($item['title'] ?? '')),
                'source' => trim((string) ($item['source'] ?? '')),
                'content' => trim((string) ($item['content'] ?? '')),
            ];
            if ($record['title'] === '' && $record['content'] === '') {
                continue;
            }
            $records[] = $record;
        }

        if ($records === []) {
            return '';
        }

        $encoded = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? mb_substr($encoded, 0, 5000) : '';
    }

    /** Extract textual parts without serializing multimodal image bytes. */
    protected function textContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;

                continue;
            }

            if (is_array($part)
                && ($part['type'] ?? null) === 'text'
                && is_string($part['text'] ?? null)) {
                $parts[] = $part['text'];
            }
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $options */
    public function synthesize(string $input, string $model, array $options = []): Response
    {
        if (trim($model) === '') {
            throw new LlmException('TTS provider is not configured.');
        }

        $response = $this->send(
            $this->request(),
            '/audio/speech',
            array_merge([
                'model' => $model,
                'input' => $input,
                'response_format' => 'mp3',
            ], $options),
        );

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        return $response;
    }

    public function stream(array $messages, string $model, array $options = []): iterable
    {
        $response = $this->send(
            $this->request()->withOptions(['stream' => true]),
            '/chat/completions',
            $this->payload($messages, $model, $options + ['stream' => true]),
        );

        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        try {
            while (! $stream->eof()) {
                // Read small chunks so downstream SSE clients receive deltas as
                // soon as the provider emits them instead of waiting for a
                // large buffer to fill.
                $buffer .= $stream->read(1024);
                [$lines, $buffer] = $this->splitLines($buffer);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        return;
                    }

                    $decoded = json_decode($data, true);
                    if (is_array($decoded)) {
                        yield $decoded;
                    }
                }
            }

            if (trim($buffer) !== '') {
                $line = trim($buffer);
                if (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                    if ($data !== '[DONE]' && is_array($decoded = json_decode($data, true))) {
                        yield $decoded;
                    }
                }
            }
        } finally {
            $stream->close();
        }
    }

    protected function request(): PendingRequest
    {
        $key = config('ai.openrouter.api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new LlmException('AI provider is not configured.');
        }

        $request = Http::baseUrl(rtrim((string) config('ai.openrouter.base_url'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->withHeaders([
                'HTTP-Referer' => config('ai.openrouter.site_url'),
                'X-Title' => config('ai.openrouter.app_name'),
            ])
            ->timeout((int) config('ai.openrouter.timeout', 90))
            ->connectTimeout((int) config('ai.openrouter.connect_timeout', 10));

        $retries = (int) config('ai.openrouter.max_retries', 1);

        if ($retries <= 0) {
            return $request;
        }

        return $request->retry(
            $retries,
            static fn (int $attempt): int => min(2000, 250 * (2 ** max(0, $attempt - 1))),
            static function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if (! $exception instanceof RequestException) {
                    return false;
                }

                $status = $exception->response->status();

                return in_array($status, [408, 409, 425, 429], true)
                    || $status >= 500;
            },
        );
    }

    /** @param array<string, mixed> $payload */
    protected function send(PendingRequest $request, string $path, array $payload): Response
    {
        try {
            return $request->post($path, $payload);
        } catch (RequestException $exception) {
            throw $this->exceptionFromResponse($exception->response);
        } catch (ConnectionException $exception) {
            throw new LlmException('AI provider connection failed.', null, null, $exception);
        }
    }

    /** @return array<string, mixed> */
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function payload(array $messages, string $model, array $options): array
    {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => config('ai.models.temperature', 0.2),
            'max_tokens' => config('ai.models.max_tokens', 2048),
        ], $options);

        // OpenRouter reports usage in a final SSE chunk only when explicitly
        // requested. Keep this opt-out friendly for non-streaming providers.
        if (($payload['stream'] ?? false) === true) {
            $payload['stream_options'] = array_merge([
                'include_usage' => true,
            ], is_array($payload['stream_options'] ?? null) ? $payload['stream_options'] : []);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    protected function decodeResponse(Response $response): array
    {
        if ($response->failed()) {
            throw $this->exceptionFromResponse($response);
        }

        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        $json['provider_request_id'] = $response->header('x-request-id');

        return $json;
    }

    protected function exceptionFromResponse(Response $response): LlmException
    {
        $message = data_get($response->json(), 'error.message')
            ?? data_get($response->json(), 'message')
            ?? 'AI provider request failed.';

        $headerRequestId = $response->header('x-request-id');
        $bodyRequestId = data_get($response->json(), 'id');
        $requestId = filled($headerRequestId)
            ? $headerRequestId
            : (is_string($bodyRequestId) ? $bodyRequestId : null);

        return new LlmException(
            is_string($message) ? $message : 'AI provider request failed.',
            $response->status(),
            $requestId,
        );
    }

    /** @return array{0: array<int, string>, 1: string} */
    protected function splitLines(string $buffer): array
    {
        $lines = preg_split('/\r?\n/', $buffer) ?: [];
        $remainder = array_pop($lines) ?? '';

        return [$lines, $remainder];
    }
}
