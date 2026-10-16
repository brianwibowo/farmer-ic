<?php

return [

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
        'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'Farmer IC')),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 90),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 1),
    ],

    'models' => [
        'primary' => (env('AI_PRIMARY_MODEL') && env('AI_PRIMARY_MODEL') !== 'google/gemini-3.8-flash')
            ? env('AI_PRIMARY_MODEL')
            : env('AI_GPT_PRIMARY_MODEL', 'openai/gpt-6-luna-pro'),
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
        'max_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 2048),
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
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 30),
    ],

];
