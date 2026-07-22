<?php
return [
    [
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-5.4-mini',
        'label' => 'OpenAI GPT 5.4 mini',
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
            'web_search' => 'native',
        ],
        'default_params' => [
            // Balanced defaults (0.7/0.9) for consistent responses; both params are sent simultaneously to OpenAI.
            'temp' => env('MODELS_OPENAI_GPT5_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_OPENAI_GPT5_PARAMS_TOP_P', 0.9),
        ],
    ],
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
            // 'web_search' => 'native',
            // Reasoning-only model: OpenAI rejects temperature/top_p outright for this model.
            'temperature' => 'unsupported',
        ],
        'default_params' => [],
    ],
    [
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-5.4-nano',
        'label' => 'OpenAI GPT 5.4 nano',
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
            'web_search' => 'native',
        ],
        'default_params' => [
            // Balanced defaults (0.7/0.9) for consistent responses; both params are sent simultaneously to OpenAI.
            'temp' => env('MODELS_OPENAI_GPT5_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_OPENAI_GPT5_PARAMS_TOP_P', 0.9),
        ],
    ],

];
