<?php

namespace App\ReadModels;

use App\Models\Contest;
use App\Services\JudgeScoreAggregationService;

final class JudgedTabulationReadModel
{
    public function __construct(private readonly ?JudgeScoreAggregationService $aggregation = null) {}

    /** @return array<string, mixed> */
    public function forContest(Contest $contest): array
    {
        return ($this->aggregation ?? new JudgeScoreAggregationService)->aggregate($contest);
    }
}
