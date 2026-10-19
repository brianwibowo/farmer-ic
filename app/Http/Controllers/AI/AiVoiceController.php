<?php

namespace App\Http\Controllers\AI;

use App\AI\Contracts\LlmGateway;
use App\AI\Exceptions\LlmException;
use App\AI\Services\OpenRouterGateway;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class AiVoiceController extends Controller
{
    public function __construct(private readonly LlmGateway $gateway) {}

    public function transcribe(Request $request, Team $current_team): JsonResponse
    {
        abort_unless((bool) config('ai.voice.enabled', false), 503, 'Voice transcription is not configured.');

        $request->validate([
            'language' => ['nullable', 'string', 'max:16', 'regex:/^[a-z]{2,3}-[A-Z]{2,4}$/'],
            'audio' => [
                'required',
                'file',
                'max:'.config('ai.voice.max_size_kb', 25600),
                'mimetypes:'.implode(',', config('ai.voice.allowed_mimes', [])),
            ],
        ]);

        /** @var UploadedFile $audio */
        $audio = $request->file('audio');
        $path = $audio->getRealPath();
        $contents = $path ? file_get_contents($path) : false;

        if ($contents === false || $contents === '') {
            return response()->json(['message' => 'Audio tidak dapat dibaca.'], 422);
        }

        $mime = $audio->getMimeType() ?: $audio->getClientMimeType();
        $format = match ($mime) {
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/x-m4a' => 'mp4',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            default => 'webm',
        };
        $language = (string) $request->input('language', config('ai.voice.language', 'id-ID'));

        $messages = [[
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Transkripsikan audio berikut dengan tepat. Bahasa: {$language}. Keluarkan hanya teks transkrip tanpa komentar tambahan.",
                ],
                [
                    'type' => 'input_audio',
                    'input_audio' => [
                        'data' => base64_encode($contents),
                        'format' => $format,
                    ],
                ],
            ],
        ]];
        $models = array_values(array_unique(array_filter([
            config('ai.voice.stt_model'),
            ...((array) config('ai.voice.stt_fallbacks', [])),
        ], fn ($model): bool => is_string($model) && trim($model) !== '')));
        $result = null;

        foreach ($models as $model) {
            try {
                $result = $this->gateway->complete($messages, $model, [
                    'temperature' => 0,
                    'max_tokens' => config('ai.voice.max_output_tokens', 512),
                ]);
                break;
            } catch (LlmException $exception) {
                if ($exception->status !== null && $exception->status < 500 && $exception->status !== 408 && $exception->status !== 429) {
                    break;
                }
            } catch (\Throwable) {
                // Try the next compatible audio model.
            }
        }

        if (! is_array($result)) {
            return response()->json(['message' => 'Layanan transkripsi sedang tidak tersedia.'], 503);
        }

        $transcript = data_get($result, 'choices.0.message.content');
        if (is_array($transcript)) {
            $transcript = collect($transcript)
                ->pluck('text')
                ->filter(fn ($text): bool => is_string($text))
                ->implode(' ');
        }

        if (! is_string($transcript) || trim($transcript) === '') {
            return response()->json(['message' => 'Transkrip tidak tersedia.'], 422);
        }

        return response()->json(['transcript' => trim($transcript)]);
    }

    public function synthesize(Request $request, Team $current_team): Response|JsonResponse
    {
        abort_unless((bool) config('ai.voice.enabled', false), 503, 'Voice synthesis is not configured.');

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:6000'],
            'voice' => ['nullable', 'string', 'max:64'],
            'response_format' => ['nullable', 'in:mp3,pcm'],
            'speed' => ['nullable', 'numeric', 'between:0.5,2'],
        ]);

        $model = (string) config('ai.voice.tts_model', '');
        if ($model === '') {
            return response()->json(['message' => 'TTS model belum dikonfigurasi.'], 503);
        }

        try {
            $audio = $this->gateway instanceof OpenRouterGateway
                ? $this->gateway->synthesize((string) $validated['text'], $model, array_filter([
                    'voice' => $validated['voice'] ?? config('ai.voice.tts_voice'),
                    'response_format' => $validated['response_format'] ?? 'mp3',
                    'speed' => $validated['speed'] ?? null,
                ], static fn ($value): bool => $value !== null && $value !== ''))
                : throw new LlmException('TTS gateway is not available.');
        } catch (LlmException) {
            return response()->json(['message' => 'Layanan pembacaan suara sedang tidak tersedia.'], 503);
        } catch (\Throwable) {
            return response()->json(['message' => 'Layanan pembacaan suara sedang tidak tersedia.'], 503);
        }

        return response($audio->body(), 200, [
            'Content-Type' => $audio->header('Content-Type') ?: 'audio/mpeg',
            'Cache-Control' => 'private, no-store',
            'X-Generation-Id' => $audio->header('X-Generation-Id') ?: '',
        ]);
    }
}
