<?php

$configuredPrimaryModel = trim((string) env('AI_PRIMARY_MODEL', ''));
$enterpriseModel = trim((string) env('AI_ENTERPRISE_MODEL', ''));
$legacyEnterpriseModel = trim((string) env('AI_GPT_PRIMARY_MODEL', ''));
$defaultPrimaryModel = $enterpriseModel !== ''
    ? $enterpriseModel
    : ($legacyEnterpriseModel !== '' ? $legacyEnterpriseModel : 'google/gemini-3.8-flash');

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
        // An explicit primary model always wins. Enterprise/legacy aliases
        // are only used when no primary is configured; a fast Flash default
        // keeps a missing environment variable from silently selecting a
        // slow reasoning model and making the UI appear stalled.
        'primary' => $configuredPrimaryModel !== ''
            ? $configuredPrimaryModel
            : $defaultPrimaryModel,
        'text_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_TEXT_FALLBACK_MODELS', 'google/gemini-3.7-flash,openai/gpt-6-luna')),
        ))),
        'vision_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_VISION_FALLBACK_MODELS', 'openai/gpt-6-luna-pro,openai/gpt-4o,google/gemini-3.8-flash')),
        ))),
        // Keep model selection explicit and reversible.  Production can opt
        // into a low-latency text model while retaining the enterprise model
        // as the next candidate when quality or provider availability needs
        // the stronger route.
        'fast_text_model' => env('AI_FAST_TEXT_MODEL'),
        'prefer_fast_text' => (bool) env('AI_PREFER_FAST_TEXT_MODEL', false),
        'infographic_model' => env('AI_INFOGRAPHIC_MODEL', 'google/gemini-2.5-flash-image'),
        'infographic_fallbacks' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_INFOGRAPHIC_FALLBACK_MODELS', 'google/gemini-3.1-flash-image,google/gemini-3-pro-image')),
        ))),
        // Keep enough completion room for careful agricultural explanations;
        // deployment can lower this with AI_MAX_OUTPUT_TOKENS when latency or
        // cost limits are tighter.
        'max_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4096),
        // Text answers are deliberately bounded independently from image and
        // voice calls.  The mobile answer format is concise, so reserving a
        // 1k-token budget prevents a slow model from spending tens of seconds
        // on an unnecessarily large completion.  Keep the legacy
        // AI_RAG_MAX_OUTPUT_TOKENS name as a deployment-friendly alias.
        'response_max_tokens' => (int) env(
            'AI_RESPONSE_MAX_OUTPUT_TOKENS',
            env('AI_RAG_MAX_OUTPUT_TOKENS', 1024),
        ),
        // OpenRouter only receives this option when explicitly configured;
        // unsupported models therefore retain their provider defaults.
        'reasoning_effort' => env('AI_REASONING_EFFORT'),
        'infographic_max_tokens' => (int) env('AI_INFOGRAPHIC_MAX_OUTPUT_TOKENS', 1536),
        'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    ],

    'knowledge' => [
        'facts_path' => env('AI_KNOWLEDGE_FACTS_PATH', resource_path('ai/knowledge/app-facts.md')),
        'policy_path' => env('AI_KNOWLEDGE_POLICY_PATH', resource_path('ai/knowledge/assistant-policy.md')),
        'demo_guides_path' => env('AI_KNOWLEDGE_DEMO_GUIDES_PATH', resource_path('ai/knowledge/demo-guides.md')),
        'adaptive_enabled' => (bool) env('AI_ADAPTIVE_KNOWLEDGE_ENABLED', false),
    ],

    'rag' => [
        'enabled' => (bool) env('AI_RAG_ENABLED', true),
        'retrieval_enabled' => (bool) env('AI_RAG_RETRIEVAL_ENABLED', true),
        'vector_enabled' => (bool) env('AI_RAG_VECTOR_ENABLED', false),
        // Observe citation validation first, then enable strict abstention in
        // a canary or quality gate once the golden set is passing.
        'require_citations' => (bool) env('AI_RAG_REQUIRE_CITATIONS', false),
        'shadow_mode' => (bool) env('AI_RAG_SHADOW_MODE', false),
        'top_k' => (int) env('AI_RAG_TOP_K', 4),
        'min_score' => (float) env('AI_RAG_MIN_EVIDENCE_SCORE', 0.22),
        'vector_min_score' => (float) env('AI_RAG_VECTOR_MIN_SCORE', 0.30),
        'vector_candidate_k' => (int) env('AI_RAG_VECTOR_CANDIDATE_K', 12),
        'vector_semantic_weight' => (float) env('AI_RAG_VECTOR_SEMANTIC_WEIGHT', 0.85),
        'index_version' => env('AI_RAG_INDEX_VERSION', 'embed-v1-chunk-v1'),
        'chunk_chars' => (int) env('AI_RAG_CHUNK_CHARS', 1800),
        'chunk_overlap_chars' => (int) env('AI_RAG_CHUNK_OVERLAP_CHARS', 200),
        'chunking_version' => env('AI_RAG_CHUNKING_VERSION', 'chunk-v1'),
        'schema_version' => env('AI_RAG_SCHEMA_VERSION', 'grounded-v1'),
        'router_version' => env('AI_RAG_ROUTER_VERSION', 'router-v1'),
        // Filesystem metadata for idempotent/reconciled Qdrant indexing.
        'manifest_path' => env('AI_RAG_MANIFEST_PATH', storage_path('app/private/ai-index')),
    ],

    'embeddings' => [
        'base_url' => env('AI_EMBEDDING_BASE_URL', env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1')),
        'api_key' => env('AI_EMBEDDING_API_KEY', env('OPENROUTER_API_KEY')),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
        'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'Farmer IC')),
        'model' => env('AI_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('AI_EMBEDDING_BATCH_SIZE', 32),
        'timeout' => (int) env('AI_EMBEDDING_TIMEOUT', 8),
        'connect_timeout' => (int) env('AI_EMBEDDING_CONNECT_TIMEOUT', 3),
        'cache_enabled' => (bool) env('AI_EMBEDDING_CACHE_ENABLED', true),
        'cache_ttl' => (int) env('AI_EMBEDDING_CACHE_TTL', 600),
    ],

    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://qdrant:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'farmer_ic_knowledge'),
        'timeout' => (int) env('QDRANT_TIMEOUT', 3),
        'connect_timeout' => (int) env('QDRANT_CONNECT_TIMEOUT', 3),
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
        'facts_chars' => (int) env('AI_FACTS_PROMPT_CHARS', 24000),
        'adaptive_chars' => (int) env('AI_ADAPTIVE_PROMPT_CHARS', 5000),
        'infographic_policy_chars' => (int) env('AI_INFOGRAPHIC_POLICY_CHARS', 4000),
        'infographic_facts_chars' => (int) env('AI_INFOGRAPHIC_FACTS_CHARS', 16000),
        'infographic_adaptive_chars' => (int) env('AI_INFOGRAPHIC_ADAPTIVE_CHARS', 3000),
    ],

];
