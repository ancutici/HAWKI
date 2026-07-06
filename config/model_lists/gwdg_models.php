<?php

return [
    [
        'active' => env('MODELS_GWDG_GEMMA_4_31B_IT_ACTIVE', true),
        'id' => 'gemma-4-31b-it',
        'label' => 'GWDG Gemma 4 31B Instruct',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false,
            'file_upload' => env('MODELS_GWDG_GEMMA_4_31B_IT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_GEMMA_4_31B_IT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Google generation_config.json for Gemma 4 sets temp=1.0 and top_p=0.95
            'temp' => env('MODELS_GWDG_GEMMA_4_31B_IT_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GEMMA_4_31B_IT_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_ACTIVE', true),
        'id' => 'mistral-large-3-675b-instruct-2512',
        'label' => 'GWDG Mistral Large 3 675B Instruct 2512',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_TOOLS_VISION', true),
        ],
        'default_params' => [
            // GWDG recommends near-deterministic temp (<0.1) for typical tasks; top_p=0.95 is the standard vLLM value
            'temp' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TEMP', 0.1),
            'top_p' => env('MODELS_GWDG_MISTRAL_LARGE_3_675B_INSTRUCT_2512_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_5_397B_A17B_ACTIVE', true),
        'id' => 'qwen3.5-397b-a17b',
        'label' => 'GWDG Qwen 3.5 397B A17B',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
            'thought',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Thinking-mode model; tool calling conflicts with chain-of-thought output
            'file_upload' => env('MODELS_GWDG_QWEN3_5_397B_A17B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_5_397B_A17B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Thinking mode: model card recommends temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_5_397B_A17B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_5_397B_A17B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_6_35B_A3B_ACTIVE', true),
        'id' => 'qwen3.6-35b-a3b',
        'label' => 'GWDG Qwen 3.6 35B A3B',
        'input' => [
            'text',
            'image',
        ],
        'output' => [
            'text',
            'thought',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => false, // Thinking-mode model; tool calling conflicts with chain-of-thought output
            'file_upload' => env('MODELS_GWDG_QWEN3_6_35B_A3B_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_6_35B_A3B_TOOLS_VISION', true),
        ],
        'default_params' => [
            // Thinking mode: model card recommends temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_6_35B_A3B_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_6_35B_A3B_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_CODER_NEXT_ACTIVE', true),
        'id' => 'qwen3-coder-next',
        'label' => 'GWDG Qwen 3 Coder Next',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_CODER_NEXT_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // Successor to qwen3-coder-30b-a3b-instruct; model card best practices: temp=0.7, top_p=0.8
            'temp' => env('MODELS_GWDG_QWEN3_CODER_NEXT_PARAMS_TEMP', 0.7),
            'top_p' => env('MODELS_GWDG_QWEN3_CODER_NEXT_PARAMS_TOP_P', 0.8),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_ACTIVE', true),
        'id' => 'qwen3-omni-30b-a3b-instruct',
        'label' => 'GWDG Qwen 3 Omni 30B A3B Instruct',
        'input' => [
            'text',
            'image',
            'audio',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_FILE_UPLOAD', true),
            'vision' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_TOOLS_VISION', true),
        ],
        'default_params' => [
            // vLLM examples for Qwen 3 Omni use temp=0.6 and top_p=0.95
            'temp' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TEMP', 0.6),
            'top_p' => env('MODELS_GWDG_QWEN3_OMNI_30B_A3B_INSTRUCT_PARAMS_TOP_P', 0.95),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_ACTIVE', true),
        'id' => 'openai-gpt-oss-120b',
        'label' => 'GWDG OpenAI GPT OSS 120B',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // No official sampling recommendations; OpenAI API defaults temp=1.0 and top_p=1.0 used
            'temp' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_OPENAI_GPT_OSS_120B_PARAMS_TOP_P', 1.0),
        ],
    ],
    [
        'active' => env('MODELS_GWDG_GLM_4_7_ACTIVE', true),
        'id' => 'glm-4.7',
        'label' => 'GWDG GLM 4.7',
        'input' => [
            'text',
        ],
        'output' => [
            'text',
        ],
        'tools' => [
            'stream' => true,
            'tool_calling' => true,
            'file_upload' => env('MODELS_GWDG_GLM_4_7_TOOLS_FILE_UPLOAD', true),
        ],
        'default_params' => [
            // GWDG recommended values: temp=1.0, top_p=0.95
            'temp' => env('MODELS_GWDG_GLM_4_7_PARAMS_TEMP', 1.0),
            'top_p' => env('MODELS_GWDG_GLM_4_7_PARAMS_TOP_P', 0.95),
        ],
    ],
];
