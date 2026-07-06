<?php

/**
 * Token pricing per model in USD per 1 million tokens.
 * Used to calculate and display usage costs to users.
 *
 * For models not listed here, a cost of 0.00 is assumed (e.g. all GWDG-hosted models).
 */
return [
    // Anthropic
    'claude-haiku-4-5'            => ['input' => 1.00,  'output' => 5.00],
    'claude-haiku-4-5-20251001'   => ['input' => 1.00,  'output' => 5.00],
    'claude-sonnet-5'             => ['input' => 2.00,  'output' => 15.00],
    'claude-sonnet-4-5-20251001'  => ['input' => 3.00,  'output' => 15.00],

    // OpenAI
    'gpt-5.4'                     => ['input' => 2.50,  'output' => 15.00],
    'gpt-5.4-mini'                => ['input' => 0.75,  'output' => 4.50],
    'gpt-5.4-nano'                => ['input' => 0.20,  'output' => 1.25],

    // Default fallback — covers all GWDG models and any unlisted model
    'default'                     => ['input' => 0.00,  'output' => 0.00],
];
