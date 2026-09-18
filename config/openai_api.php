<?php

/*
|--------------------------------------------------------------------------
|   OpenAI-kompatible API unter /api/v1/
|--------------------------------------------------------------------------
|
|   Diese Routen reichen den Request unveraendert an den Upstream-Provider
|   durch. Das ist moeglich, weil die GWDG Academic Cloud selbst die
|   OpenAI-Chat-Completions-API spricht. Uebersetzt wird nichts - jede
|   Umformung waere eine Gelegenheit, ein Feld zu verlieren, das ein Client
|   (Continue, Cline, aider, LangChain, ...) braucht.
|
|   Autorisierung, Modell-Freigabe ('external' in config/model_lists/*.php)
|   und Abrechnung laufen unveraendert ueber dieselbe Maschinerie wie
|   POST /api/ai-req.
|
*/

return [

    /*
     * Provider, die ueber /api/v1/ erreichbar sind, mit der URL ihres
     * Chat-Completions-Endpunkts.
     *
     * Ein Modell, dessen Provider hier NICHT steht, ist ueber diese API nicht
     * nutzbar - auch dann nicht, wenn es 'external' => true traegt. Grund:
     * durchgereicht werden darf nur an einen Endpunkt, der das
     * OpenAI-Chat-Completions-Format spricht.
     *
     * ACHTUNG bei OpenAI: der Provider 'openAi' zeigt in model_providers.php
     * auf die Responses-API (/v1/responses, Feld 'input' statt 'messages'), die
     * das Webinterface benutzt. Dorthin laesst sich ein Chat-Completions-Body
     * nicht durchreichen, darum steht hier die Chat-Completions-URL - OpenAI
     * bedient beide Formate parallel. Der API-Key kommt in beiden Faellen aus
     * model_providers.php.
     */
    'chat_completions_urls' => [
        'gwdg' => env('OPENAI_API_GWDG_CHAT_URL', 'https://chat-ai.academiccloud.de/v1/chat/completions'),
        'openAi' => env('OPENAI_API_OPENAI_CHAT_URL', 'https://api.openai.com/v1/chat/completions'),
    ],

    /*
     * Dasselbe fuer den Legacy-Endpunkt /v1/completions (Prompt statt Dialog), den die
     * Entwicklungswerkzeuge fuer die Inline-Autovervollstaendigung benutzen. Die
     * Modell-Freigabe ist dieselbe wie oben - ein Provider, der hier fehlt, ist ueber
     * /api/v1/completions nicht erreichbar.
     *
     * 'openAi' fehlt hier absichtlich: OpenAI beantwortet seine Chat-Modelle auf
     * /v1/completions mit 404 "This is a chat model and not supported in the
     * v1/completions endpoint" (geprueft am 2026-09-18 mit gpt-5.6-luna). Ein
     * Eintrag wuerde das Modell in der Autovervollstaendigung anbieten und dort
     * bei jeder Anfrage scheitern; ohne ihn antwortet die API mit einem
     * lesbaren 403.
     */
    'completions_urls' => [
        'gwdg' => env('OPENAI_API_GWDG_COMPLETIONS_URL', 'https://chat-ai.academiccloud.de/v1/completions'),
    ],

    /*
     * Parameter, die ein Upstream anders nennt als der Client sie schickt.
     *
     * Normalerweise wird hier nichts umgeformt. Diese Liste ist die Ausnahme fuer
     * Felder, die der Upstream mit 400 ablehnt, obwohl der Client sie voellig
     * regelkonform sendet - ein Fall, den die Vorgabe mit "stillschweigend
     * verwerfen, niemals ablehnen" abdeckt.
     *
     * OpenAI: 'max_tokens' ist auf chat/completions durch 'max_completion_tokens'
     * ersetzt. Die 5.6er-Modelle antworten auf 'max_tokens' mit
     * "Unsupported parameter: 'max_tokens' is not supported with this model"
     * (geprueft am 2026-09-18). Continue und Cline schicken 'max_tokens'.
     *
     * Ein bereits vorhandener Zielname gewinnt - schickt ein Client beide Felder,
     * bleibt das modernere stehen.
     */
    'parameter_renames' => [
        'openAi' => [
            'max_tokens' => 'max_completion_tokens',
        ],
    ],

    /*
     * Sekunden, die eine NICHT gestreamte Anfrage dauern darf.
     *
     * Muss unter dem Apache-Timeout bleiben (Timeout 120 in
     * /etc/apache2/conf-enabled/20-basics.conf, von dem ProxyTimeout erbt).
     * Sonst kappt Apache die Verbindung zuerst, und der Client bekommt eine
     * HTML-Fehlerseite statt JSON - genau das Verhalten, das bei glm-4.7
     * beobachtet wurde. Mit diesem Limit antwortet stattdessen die Anwendung
     * mit einem lesbaren 504 und dem Hinweis auf Streaming.
     */
    /*
     * Embeddings und Reranking.
     *
     * Bewusst NICHT ueber das Modellregister: das sind keine Chat-Modelle. Ein Eintrag in
     * config/model_lists/*.php wuerde sie in der Modellauswahl des Webinterfaces auftauchen
     * lassen, wo sie nicht benutzbar sind. Die Freigabeliste hier ist die einzige Stelle,
     * die darueber entscheidet - unabhaengig vom 'external'-Flag der Chat-Modelle.
     *
     * 'provider' verweist auf einen Eintrag in model_providers.php und liefert nur den
     * API-Key; die URL steht hier, weil es andere Endpunkte als chat/completions sind.
     *
     * Ein Modell freischalten heisst: ID in 'models' eintragen. Die IDs muessen exakt so
     * geschrieben sein, wie der Provider sie kennt - die Clients tragen sie woertlich in
     * ihre Konfiguration ein.
     */
    'embeddings' => [
        'provider' => env('OPENAI_API_EMBEDDINGS_PROVIDER', 'gwdg'),
        'url' => env('OPENAI_API_EMBEDDINGS_URL', 'https://chat-ai.academiccloud.de/v1/embeddings'),
        'models' => [
            'e5-mistral-7b-instruct',
        ],
    ],

    'rerank' => [
        'provider' => env('OPENAI_API_RERANK_PROVIDER', 'gwdg'),
        'url' => env('OPENAI_API_RERANK_URL', 'https://chat-ai.academiccloud.de/v1/rerank'),
        'models' => [
            'qwen3-reranker-8b',
        ],
    ],

    /*
     * Ob GET /api/v1/models die Embedding- und Rerank-Modelle mitlistet.
     *
     * OpenAI selbst tut das, und Werkzeuge wie Continue brauchen die IDs, um Codebase-Suche
     * einzurichten. Der Preis dafuer: Clients, die ihre Chat-Modellauswahl aus dieser Liste
     * befuellen, zeigen sie dort ebenfalls an, wo sie nicht funktionieren.
     */
    'list_auxiliary_models' => (bool) env('OPENAI_API_LIST_AUXILIARY_MODELS', true),

    'request_timeout' => (int) env('OPENAI_API_REQUEST_TIMEOUT', 110),

    /*
     * Sekunden, die ein Stream nichts liefern darf, bevor abgebrochen wird.
     * Reasoning-Modelle denken vor dem ersten Token laenger still vor sich hin,
     * darum nicht zu knapp. Solange Chunks fliessen, greift der Apache-Timeout
     * nicht - er ist ein Inaktivitaets-Timeout.
     */
    'stream_idle_timeout' => (int) env('OPENAI_API_STREAM_IDLE_TIMEOUT', 90),

];
