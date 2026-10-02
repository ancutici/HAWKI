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
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-6.1-sol',
        'external' => false,
        'label' => 'OpenAI GPT 6.1 Sol',
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
            // Web-Suche für alle OpenAI-Modelle aktiviert und am 2026-10-02 manuell verifiziert.
            'web_search' => 'native',
            // Reasoning model: OpenAI rejects temperature != 1 (geprueft am 2026-10-02).
            'temperature' => 'unsupported',
        ],
        'default_params' => [],
    ],
    [
        // Ersetzt gpt-5.6-luna als default_model/web_search/file_upload/vision und als
        // title_generator/prompt_improver/summarizer, siehe config/model_providers.php.
        'active'=> env('MODELS_OPENAI_GPT5_ACTIVE', true),
        'id' => 'gpt-6-luna',
        'external' => true,
        'label' => 'OpenAI GPT 6 Luna',
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
            // Web-Suche für alle OpenAI-Modelle aktiviert und am 2026-10-02 manuell verifiziert.
            'web_search' => 'native',
            // Same as gpt-6.1-sol: OpenAI rejects temperature != 1 (geprueft am 2026-10-02).
            'temperature' => 'unsupported',
        ],
        'default_params' => [],
    ],

];
