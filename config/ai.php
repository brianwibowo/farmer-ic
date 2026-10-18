<?php

$enterpriseModel = trim((string) env('AI_ENTERPRISE_MODEL', ''));
if ($enterpriseModel === '') {
    $enterpriseModel = trim((string) env('AI_GPT_PRIMARY_MODEL', 'openai/gpt-5.2-pro'));
}
$configuredPrimaryModel = trim((string) env('AI_PRIMARY_MODEL', ''));

return [

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
        'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'Farmer IC')),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 90),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 1),
        // A streamed LLM response may outlive PHP's default 30 second limit.
        // Keep the ceiling explicit so deployments can tune it to their
        // gateway and web-server timeout budget.
        'execution_timeout' => (int) env('AI_EXECUTION_TIMEOUT', 120),
    ],

    'models' => [
        // OpenRouter's enterprise alias keeps the primary model on a managed,
        // high-quality route without exposing a provider key in the browser.
        // The old demo sentinel is upgraded automatically; an explicit custom
        // model still wins when configured.
        'primary' => $configuredPrimaryModel === '' ||
            $configuredPrimaryModel === 'google/gemini-3.8-flash'
            ? $enterpriseModel
            : $configuredPrimaryModel,
        'text_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_TEXT_FALLBACK_MODELS', 'openai/gpt-5.5-pro,openai/gpt-5.2-pro,openai/gpt-4o')),
        ))),
        'vision_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_VISION_FALLBACK_MODELS', 'openai/gpt-6-luna-pro,openai/gpt-4o,google/gemini-3.8-flash')),
        ))),
        'infographic_model' => env('AI_INFOGRAPHIC_MODEL', 'google/gemini-2.5-flash-image'),
        'infographic_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_INFOGRAPHIC_FALLBACK_MODELS', 'google/gemini-3.1-flash-image,google/gemini-3-pro-image')),
        ))),
        'infographic_demo_asset' => env('AI_INFOGRAPHIC_DEMO_ASSET', '/images/ai-infographics/smart-farming-overview.png'),
        // Keep enough completion room for careful agricultural explanations;
        // deployment can lower this with AI_MAX_OUTPUT_TOKENS when latency or
        // cost limits are tighter.
        'max_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4096),
        'infographic_max_tokens' => (int) env('AI_INFOGRAPHIC_MAX_OUTPUT_TOKENS', 1536),
        'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    ],

    'knowledge' => [
        'facts_path' => env('AI_KNOWLEDGE_FACTS_PATH', resource_path('ai/knowledge/app-facts.md')),
        'policy_path' => env('AI_KNOWLEDGE_POLICY_PATH', resource_path('ai/knowledge/assistant-policy.md')),
        'adaptive_enabled' => (bool) env('AI_ADAPTIVE_KNOWLEDGE_ENABLED', false),
    ],

    'attachments' => [
        'disk' => env('AI_ATTACHMENTS_DISK', 'local'),
        'max_files' => (int) env('AI_MAX_ATTACHMENTS', 3),
        'max_size_kb' => (int) env('AI_MAX_ATTACHMENT_SIZE_KB', 10240),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
    ],

    'voice' => [
        'enabled' => (bool) env('AI_VOICE_ENABLED', true),
        'language' => env('AI_VOICE_LANGUAGE', 'id-ID'),
        'stt_model' => env('AI_STT_MODEL', 'qwen/qwen3.8-omni-flash'),
        'stt_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_STT_FALLBACK_MODELS', 'google/gemini-3.8-flash')),
        ))),
        'tts_model' => env('AI_TTS_MODEL'),
        'tts_voice' => env('AI_TTS_VOICE'),
        'max_output_tokens' => (int) env('AI_STT_MAX_OUTPUT_TOKENS', 512),
        'max_size_kb' => (int) env('AI_VOICE_MAX_SIZE_KB', 25600),
        'allowed_mimes' => [
            'audio/webm',
            'video/webm',
            'audio/ogg',
            'audio/wav',
            'audio/mpeg',
            'audio/mp4',
            'audio/x-m4a',
        ],
    ],

    'limits' => [
        'max_message_chars' => (int) env('AI_MAX_MESSAGE_CHARS', 12000),
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 12),
        'max_history_chars' => (int) env('AI_MAX_HISTORY_CHARS', 18000),
        'max_attachment_prompt_bytes' => (int) env('AI_MAX_ATTACHMENT_PROMPT_BYTES', 4000000),
        'policy_chars' => (int) env('AI_POLICY_PROMPT_CHARS', 8000),
        'facts_chars' => (int) env('AI_FACTS_PROMPT_CHARS', 8000),
        'adaptive_chars' => (int) env('AI_ADAPTIVE_PROMPT_CHARS', 5000),
        'infographic_policy_chars' => (int) env('AI_INFOGRAPHIC_POLICY_CHARS', 4000),
        'infographic_facts_chars' => (int) env('AI_INFOGRAPHIC_FACTS_CHARS', 5000),
        'infographic_adaptive_chars' => (int) env('AI_INFOGRAPHIC_ADAPTIVE_CHARS', 3000),
    ],

];
