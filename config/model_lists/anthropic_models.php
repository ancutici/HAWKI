<?php

/*
 * 'external' steuert, ob ein Modell ueber die API (POST /api/ai-req) nutzbar ist.
 * Vorgabe Uni Hohenheim: per API sind aus Kostengruenden nur GWDG-Modelle erlaubt.
 * Der Key MUSS bei jedem Modell gesetzt werden - fehlt er, gilt das Modell als
 * erlaubt (siehe AiModel::isAllowedInExternalApp()).
 * Zusaetzlich muessen die ext_app-Defaults in config/model_providers.php auf
 * GWDG-Modelle zeigen, sonst zieht der Default das Modell in die externe Liste.
 */

return [
    [
        'active' => env('MODELS_ANTHROPIC_HAIKU_ACTIVE', true),
        'id' => 'claude-haiku-5-5',
        'external' => false,
        'label' => 'Claude Haiku 5.5',
        'input' => ['text', 'image'],
        'output' => ['text'],
        'tools' => [
            'stream' => true,
            'tool_calling' => false,
            'file_upload' => true,
            'vision' => true,
        ],
        'default_params' => [
            // claude-haiku-5-5 rejects temperature and top_p ("deprecated for this model", geprueft am 2026-10-12).
        ],
    ],
    [
        'active' => env('MODELS_ANTHROPIC_SONNET_ACTIVE', true),
        'id' => 'claude-sonnet-5-5',
        'external' => false,
        'label' => 'Claude Sonnet 5.5',
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
            // claude-sonnet-5-5 rejects temperature and top_p ("deprecated for this model", geprueft am 2026-10-02).
        ],
    ],
];
