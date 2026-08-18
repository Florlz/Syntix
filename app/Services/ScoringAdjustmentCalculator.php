<?php

namespace App\Services;

final class ScoringAdjustmentCalculator
{
    /** @param array<string, mixed> $configuration */
    public function calculate(array $configuration, string $code, string $inputValue, string $inputUnit): string
    {
        if (($configuration['code'] ?? null) !== $code) {
            throw new \DomainException('The adjustment code is not configured for this rule.');
        }

        if (($configuration['calculation_status'] ?? null) !== 'authorized') {
            throw new \DomainException('The deduction calculation is blocked until its rounding or calculation policy is authorized.');
        }

        $value = filter_var($inputValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($value === false) {
            throw new \DomainException('The objective adjustment input must be a non-negative whole number.');
        }

        return match ($configuration['type'] ?? null) {
            'outside_range_interval' => $this->outsideRangeInterval($configuration, $value, $inputUnit),
            'outside_range_flat' => $this->outsideRangeFlat($configuration, $value, $inputUnit),
            default => throw new \DomainException('The configured deduction type is unsupported.'),
        };
    }

    /** @param array<string, mixed> $configuration */
    private function outsideRangeInterval(array $configuration, int $value, string $unit): string
    {
        if ($unit !== 'seconds') {
            throw new \DomainException('This deduction requires a duration in seconds.');
        }

        $minimum = (int) ($configuration['minimum_seconds'] ?? -1);
        $maximum = (int) ($configuration['maximum_seconds'] ?? -1);
        $interval = (int) ($configuration['interval_seconds'] ?? 0);
        $points = (int) ($configuration['points_per_interval'] ?? 0);
        $rounding = $configuration['rounding_policy'] ?? null;
        if ($minimum < 0 || $maximum < $minimum || $interval <= 0 || $points <= 0) {
            throw new \DomainException('The interval deduction configuration is incomplete.');
        }
        if (! in_array($rounding, ['ceiling', 'floor', 'nearest'], true)) {
            throw new \DomainException('An authorized interval rounding policy is required.');
        }

        $outside = $value < $minimum ? $minimum - $value : ($value > $maximum ? $value - $maximum : 0);
        $intervals = match ($rounding) {
            'ceiling' => (int) ceil($outside / $interval),
            'floor' => intdiv($outside, $interval),
            'nearest' => (int) floor(($outside + ($interval / 2)) / $interval),
        };

        return number_format($intervals * $points, 4, '.', '');
    }

    /** @param array<string, mixed> $configuration */
    private function outsideRangeFlat(array $configuration, int $value, string $unit): string
    {
        if ($unit !== 'words') {
            throw new \DomainException('This deduction requires a word count.');
        }

        $minimum = (int) ($configuration['minimum_words'] ?? -1);
        $maximum = (int) ($configuration['maximum_words'] ?? -1);
        $points = (int) ($configuration['points'] ?? 0);
        if ($minimum < 0 || $maximum < $minimum || $points <= 0) {
            throw new \DomainException('The flat deduction configuration is incomplete.');
        }

        return number_format($value < $minimum || $value > $maximum ? $points : 0, 4, '.', '');
    }
}
