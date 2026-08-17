<?php

namespace App\Data;

final readonly class CompetitionRuleMetadata
{
    /**
     * @param  list<int>  $sourcePages
     * @param  list<string>  $eventControls
     * @param  list<string>  $venueCandidates
     * @param  array<string, mixed>  $deductionConfiguration
     */
    public function __construct(
        public string $reliabilityLabel,
        public array $sourcePages,
        public array $eventControls,
        public array $venueCandidates,
        public ?string $programmeDayHint,
        public ?string $sourceBlocker,
        public array $deductionConfiguration,
    ) {}
}
