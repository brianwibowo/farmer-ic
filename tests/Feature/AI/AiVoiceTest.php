<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\LlmGateway;
use App\AI\Services\OpenRouterGateway;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ProviderRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Voice HTTP integration on the guarded PostgreSQL test schema; provider HTTP is faked. */
class AiVoiceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['role' => 'owner']);
        $this->team = Team::factory()->create(['name' => 'Tim QA Suara Kebun']);
        $this->team->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $owner->switchTeam($this->team);
        $this->actingAs($owner);

        config([
            'ai.openrouter.base_url' => 'https://ai-provider.test/api/v1',
            'ai.openrouter.api_key' => 'qa-voice-key',
            'ai.openrouter.max_retries' => 0,
            'ai.voice.enabled' => true,
            'ai.voice.stt_model' => 'qa-transcription',
            'ai.voice.stt_fallbacks' => [],
            'ai.voice.tts_model' => 'qa-speech',
            'ai.voice.tts_voice' => 'qa-indonesian',
        ]);
        $this->app->instance(LlmGateway::class, new OpenRouterGateway);
        Http::preventStrayRequests();
    }

    public function test_owner_can_transcribe_a_real_pcm_wav_and_receives_trimmed_text(): void
    {
        Http::fake([
            'https://ai-provider.test/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '  Periksa saluran air kebun.  ']]],
            ]),
        ]);

        $wave = $this->wave();
        $audio = $this->audioUpload($wave);
        $this->assertContains($audio->getMimeType(), ['audio/wav', 'audio/x-wav']);
        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $audio,
            'language' => 'id-ID',
        ], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['transcript' => 'Periksa saluran air kebun.']);

        Http::assertSent(fn (ProviderRequest $request): bool => $request->url() === 'https://ai-provider.test/api/v1/chat/completions'
            && $request['model'] === 'qa-transcription'
            && data_get($request->data(), 'messages.0.content.1.input_audio.format') === 'wav'
            && base64_decode((string) data_get($request->data(), 'messages.0.content.1.input_audio.data'), true) === $wave);
        Http::assertSentCount(1);
    }

    public function test_transcription_retries_a_transient_model_failure_and_joins_text_parts(): void
    {
        config(['ai.voice.stt_fallbacks' => ['qa-transcription-fallback']]);
        Http::fake(fn (ProviderRequest $request) => $request['model'] === 'qa-transcription'
            ? Http::response(['error' => ['message' => 'Temporary provider failure.']], 503)
            : Http::response(['choices' => [['message' => ['content' => [
                ['type' => 'text', 'text' => 'Petugas memeriksa'],
                ['type' => 'text', 'text' => 'saluran air.'],
            ]]]]]));

        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $this->audioUpload($this->wave()),
        ], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['transcript' => 'Petugas memeriksa saluran air.']);

        Http::assertSentCount(2);
        Http::assertSent(fn (ProviderRequest $request): bool => $request['model'] === 'qa-transcription-fallback');
    }

    public function test_transcription_provider_auth_failure_is_sanitized_without_retrying_other_models(): void
    {
        config(['ai.voice.stt_fallbacks' => ['qa-transcription-fallback']]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Private provider diagnostics qa-voice-key']], 401)]);

        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $this->audioUpload($this->wave()),
        ], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Layanan transkripsi sedang tidak tersedia.']);

        Http::assertSentCount(1);
    }

    public function test_fake_audio_extension_and_oversized_audio_are_rejected_before_provider_calls(): void
    {
        Http::fake();
        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $this->audioUpload('This is plain text, not recorded audio.'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('audio');

        config(['ai.voice.max_size_kb' => 2]);
        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $this->audioUpload($this->wave()),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('audio');

        Http::assertNothingSent();
    }

    public function test_voice_language_is_validated_before_provider_calls(): void
    {
        Http::fake();
        $this->post(route('ai.voice.transcribe', $this->team->slug), [
            'audio' => $this->audioUpload($this->wave()),
            'language' => 'invalid-language',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('language');
        Http::assertNothingSent();
    }

    public function test_disabled_voice_rejects_transcription_and_synthesis_before_provider_calls(): void
    {
        config(['ai.voice.enabled' => false]);
        Http::fake();

        $this->postJson(route('ai.voice.transcribe', $this->team->slug))
            ->assertStatus(503);
        $this->postJson(route('ai.voice.synthesize', $this->team->slug), ['text' => 'Periksa saluran air.'])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_owner_can_request_speech_and_receive_private_audio_bytes(): void
    {
        $audio = 'ID3-qa-speech-payload';
        Http::fake(['https://ai-provider.test/api/v1/audio/speech' => Http::response($audio, 200, [
            'Content-Type' => 'audio/mpeg',
            'X-Generation-Id' => 'qa-voice-generation',
        ])]);

        $response = $this->postJson(route('ai.voice.synthesize', $this->team->slug), [
            'text' => 'Periksa saluran air kebun.',
            'speed' => 0.9,
        ])->assertOk()->assertContent($audio)
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('X-Generation-Id', 'qa-voice-generation');

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        Http::assertSent(fn (ProviderRequest $request): bool => $request->url() === 'https://ai-provider.test/api/v1/audio/speech'
            && $request['model'] === 'qa-speech'
            && $request['input'] === 'Periksa saluran air kebun.'
            && $request['voice'] === 'qa-indonesian'
            && $request['response_format'] === 'mp3'
            && $request['speed'] === 0.9);
        Http::assertSentCount(1);
    }

    public function test_speech_provider_failure_does_not_expose_provider_diagnostics(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Private speech diagnostics qa-voice-key']], 500)]);

        $this->postJson(route('ai.voice.synthesize', $this->team->slug), ['text' => 'Periksa saluran air.'])
            ->assertStatus(503)->assertExactJson(['message' => 'Layanan pembacaan suara sedang tidak tersedia.']);

        Http::assertSentCount(1);
    }

    public function test_speech_input_and_speed_are_validated_before_provider_calls(): void
    {
        Http::fake();

        $this->postJson(route('ai.voice.synthesize', $this->team->slug), ['text' => str_repeat('a', 6001)])
            ->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->postJson(route('ai.voice.synthesize', $this->team->slug), ['text' => 'Periksa kebun.', 'speed' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('speed');

        Http::assertNothingSent();
    }

    private function audioUpload(string $contents): UploadedFile
    {
        $temporary = tmpfile();
        $this->assertIsResource($temporary);
        fwrite($temporary, $contents);
        $path = stream_get_meta_data($temporary)['uri'];
        $this->beforeApplicationDestroyed(static function () use ($temporary): void {
            fclose($temporary);
        });

        // The framework's fake File guesses MIME from the filename. Use the
        // real upload class so an audio/wav claim cannot hide text content.
        return new UploadedFile($path, 'catatan-kebun.wav', 'audio/wav', UPLOAD_ERR_OK, true);
    }

    private function wave(): string
    {
        $samples = str_repeat("\0", 3200);

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
    }
}
