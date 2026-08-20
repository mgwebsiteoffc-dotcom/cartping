<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter (single gateway for GPT-4o and free reasoning models)
    |--------------------------------------------------------------------------
    */
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'api_key' => env('OPENROUTER_API_KEY', ''),

    // Model used for the conversational store agent (Chat Completions + function calling).
    'model' => env('AI_MODEL', 'openai/gpt-4o'),

    // Model used for structured JSON extraction / planning with reasoning enabled.
    'reasoning_model' => env('AI_REASONING_MODEL', 'nvidia/nemotron-3.5-lightning:free'),

    'temperature' => (float) env('AI_TEMPERATURE', 0.4),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1200),
    'timeout' => (int) env('AI_TIMEOUT', 60),

    // OpenRouter attribution (recommended, not required).
    'http_referer' => env('OPENROUTER_HTTP_REFERER', ''),
    'site_url' => env('OPENROUTER_SITE_URL', ''),

    // Maximum function-calling turns the agent may loop before yielding to a human.
    'max_tool_rounds' => (int) env('AI_MAX_TOOL_ROUNDS', 8),

    /*
    |--------------------------------------------------------------------------
    | Agent orchestration limits
    |--------------------------------------------------------------------------
    */
    'max_rag_chunks' => (int) env('AI_MAX_RAG_CHUNKS', 6),
    'min_confidence_for_autonomous' => (float) env('AI_MIN_CONFIDENCE', 0.55),
];
