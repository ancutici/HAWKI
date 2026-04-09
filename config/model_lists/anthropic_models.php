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
            'temp' => env('MODELS_ANTHROPIC_HAIKU_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_ANTHROPIC_HAIKU_PARAMS_TOP_P', 1.0),
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
            'temp' => env('MODELS_ANTHROPIC_SONNET_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_ANTHROPIC_SONNET_PARAMS_TOP_P', 1.0),
        ],
    ],
];
