<?php

/**
 * Token pricing per model in USD per 1 million tokens.
 * Used to calculate and display usage costs to users.
 *
 * 'cache_write' / 'cache_read' apply only to providers with prompt caching
 * (currently Anthropic/Claude). Models without these keys are treated as 0,
 * i.e. caching does not affect their cost — this is the case for OpenAI and
 * GWDG models today.
 *
 * For models not listed here, a cost of 0.00 is assumed (e.g. all GWDG-hosted models).
 */
return [
    // Anthropic
    // Haiku 5.5: Preis fuer Prompts bis 100k Tokens; darueber 0.50 / 2.50 / 0.625 / 0.05 (Staffel nicht abbildbar).
    'claude-haiku-5-5'            => ['input' => 0.10,  'output' => 0.50,  'cache_write' => 0.125, 'cache_read' => 0.01],
    'claude-sonnet-5-5'           => ['input' => 2.00,  'output' => 10.00, 'cache_write' => 2.50, 'cache_read' => 0.10],

    // OpenAI
    'gpt-6.1-sol'                 => ['input' => 2.00,  'output' => 10.00],
    'gpt-6-luna'                  => ['input' => 0.10,  'output' => 0.50],

    // Default fallback — covers all GWDG models and any unlisted model
    'default'                     => ['input' => 0.00,  'output' => 0.00],
];
