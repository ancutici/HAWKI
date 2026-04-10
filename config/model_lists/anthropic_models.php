<?php
return [
    [
        'active' => env('MODELS_ANTHROPIC_HAIKU_ACTIVE', true),
        'id' => 'claude-haiku-4-5',
        'label' => 'Claude Haiku 4.5',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'tool_calling' => false,
            'file_upload' => true,
            'vision' => true,
        ],
        'default_params' => [
            // Anthropic only accepts temperature OR top_p (not both); top_p is never sent when temperature is set.
            // 0.7 gives balanced, consistent responses suitable for a university chat interface.
            'temp' => env('MODELS_ANTHROPIC_HAIKU_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_ANTHROPIC_HAIKU_PARAMS_TOP_P', 0.9),
        ],
    ],
    [
        'active' => env('MODELS_ANTHROPIC_SONNET_ACTIVE', true),
        'id' => 'claude-sonnet-4-5',
        'label' => 'Claude Sonnet 4.5',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'tool_calling' => false,
            'file_upload' => true,
            'vision' => true,
            // 'web_search' => 'native',            
        ],
        'default_params' => [
            // Anthropic only accepts temperature OR top_p (not both); top_p is never sent when temperature is set.
            // 0.7 gives balanced, consistent responses suitable for a university chat interface.
            'temp' => env('MODELS_ANTHROPIC_SONNET_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_ANTHROPIC_SONNET_PARAMS_TOP_P', 0.9),
        ],
    ],
];
