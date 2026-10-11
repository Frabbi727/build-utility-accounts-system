<?php

namespace App\Support;

class MeterGenerationResultData
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $flatsConsidered,
        public readonly int $metersCreated,
        public readonly int $metersSkipped,
        public readonly int $flatsExcluded,
        public readonly array $errors = [],
    ) {}
}
