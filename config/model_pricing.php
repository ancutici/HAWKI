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
    'claude-haiku-4-5'            => ['input' => 1.00,  'output' => 5.00,  'cache_write' => 1.25, 'cache_read' => 0.10],
    'claude-sonnet-5-5'           => ['input' => 2.00,  'output' => 10.00, 'cache_write' => 2.50, 'cache_read' => 0.20],

    // OpenAI
    'gpt-6.1-sol'                 => ['input' => 2.00,  'output' => 10.00],
    'gpt-6-luna'                  => ['input' => 0.10,  'output' => 0.50],

    // Default fallback — covers all GWDG models and any unlisted model
    'default'                     => ['input' => 0.00,  'output' => 0.00],
];
