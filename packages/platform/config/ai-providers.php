<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI provider catalog
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the providers offered in Account -> AI Keys.
    | Add a provider here (slug, display name, model list, default model,
    | testability flag, capability hints) and it appears in the UI without a
    | code change. Pricing lives in AiPricingCatalog, not here.
    |
    | Consumers may override the whole catalog via config('ai-providers.catalog').
    |
    */
    'catalog' => [
        'openai' => [
            'name' => 'OpenAI',
            'models' => ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna', 'gpt-5', 'o4-mini'],
            'default_model' => 'gpt-5.6-luna',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output', 'embeddings', 'audio', 'transcription'],
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'models' => ['claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5'],
            'default_model' => 'claude-sonnet-5',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output'],
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'models' => ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-2.5-pro'],
            'default_model' => 'gemini-3.5-flash-lite',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output', 'images'],
        ],
        'mistral' => [
            'name' => 'Mistral',
            'models' => ['mistral-medium-latest', 'mistral-small-latest', 'mistral-large-latest'],
            'default_model' => 'mistral-small-latest',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output'],
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'models' => ['deepseek-chat', 'deepseek-reasoner'],
            'default_model' => 'deepseek-chat',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output'],
        ],
        'xai' => [
            'name' => 'xAI (Grok)',
            'models' => ['grok-4.5', 'grok-4.3', 'grok-4.20-reasoning'],
            'default_model' => 'grok-4.3',
            'testable' => true,
            'capabilities' => ['chat', 'streaming', 'structured_output'],
        ],
        'ollama' => [
            'name' => 'Ollama (Local)',
            'models' => [],
            'default_model' => 'llama3.2',
            'testable' => false,
            'capabilities' => ['chat', 'streaming', 'structured_output', 'local'],
        ],
    ],
];
