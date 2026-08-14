<?php
return [
    [
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-5.6-terra',
        'label' => 'OpenAI GPT 5.6 Terra',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            // Native capabilities
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => true,
            'vision'=> true,
            // Web-Suche für alle OpenAI-Modelle aktiviert und am 2026-08-14 manuell verifiziert.
            'web_search' => 'native',
            // Reasoning-only model: OpenAI rejects temperature/top_p outright for this model.
            'temperature' => 'unsupported',
        ],
        'default_params' => [],
    ],
    [
        // Ersetzt gpt-5.4-mini (default_model/web_search/file_upload/vision) und gpt-5.4-nano
        // (title_generator/prompt_improver/summarizer) - GPT 5.6 fasst beide Kostenklassen in
        // einem Modell zusammen, siehe config/model_providers.php.
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-5.6-luna',
        'label' => 'OpenAI GPT 5.6 Luna',
        "input"=> [
            "text",
            "image"
        ],
        "output"=> [
            "text"
        ],
        'tools' => [
            // Native capabilities
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => true,
            'vision'=> true,
            // Web-Suche für alle OpenAI-Modelle aktiviert und am 2026-08-14 manuell verifiziert.
            'web_search' => 'native',
            // Same 5.6-generation reasoning model as gpt-5.6-terra: OpenAI rejects temperature/top_p outright.
            'temperature' => 'unsupported',
        ],
        'default_params' => [],
    ],

];
