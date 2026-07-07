<?php


return [

    'default' => env('FILE_CONVERTER', 'hawki_converter'),
    'fallback' => 'hawki_converter',

    'converters' => [
        'hawki_converter' => [
            'api_url' => env('HAWKI_FILE_CONVERTER_API_URL'),
            'api_key' => env('HAWKI_FILE_CONVERTER_API_KEY'),
        ],
        'gwdg_docling' =>[
            'api_url' => env('GWDG_FILE_CONVERTER_API_URL', 'https://chat-ai.academiccloud.de/v1/documents/convert'),
            'api_key' => env('GWDG_API_KEY')
        ]
    ],

    // Rough token-budget guard for extracted attachment text (~4 characters per token,
    // a commonly used estimate for English/code). Attachments whose converted content
    // exceeds this are rejected the same way as an oversized upload, instead of silently
    // risking a context-length error from the AI provider mid-conversation.
    'max_estimated_tokens' => (int) env('ATTACHMENT_MAX_ESTIMATED_TOKENS', 50000),
];
