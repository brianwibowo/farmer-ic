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
        $response = $this->send(
            $this->request(),
            '/chat/completions',
            $this->payload($messages, $model, $options),
        );

        return $this->decodeResponse($response);
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

        return is_array($json) ? $json : [];
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
