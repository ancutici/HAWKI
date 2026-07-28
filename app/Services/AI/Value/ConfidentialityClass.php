<?php

namespace App\Services\AI\Value;

enum ConfidentialityClass: string
{
    case C1 = 'C1';
    case C2 = 'C2';
    case C3 = 'C3';
    case C4 = 'C4';

    /**
     * Provider IDs (as used in config/model_providers.php) allowed to be used
     * for this confidentiality class. Null means all providers are allowed,
     * an empty array means no provider may be used.
     * @return string[]|null
     */
    public function allowedProviderIds(): ?array
    {
        return match ($this) {
            self::C1, self::C2 => null,
            self::C3 => ['gwdg'],
            self::C4 => [],
        };
    }
}
