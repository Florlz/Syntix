<?php

namespace App\Services;

final class DecimalMath
{
    public static function toScaled(string|int|float $value, int $scale): int
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $whole * (10 ** $scale)) + (int) ($fraction === '' ? 0 : $fraction);

        return $negative ? -$scaled : $scaled;
    }

    public static function fromScaled(int $value, int $scale): string
    {
        $negative = $value < 0;
        $value = abs($value);
        $factor = 10 ** $scale;
        $whole = intdiv($value, $factor);
        $fraction = $scale === 0 ? '' : str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.($scale === 0 ? '' : '.'.$fraction);
    }

    public static function divideRounded(int $numerator, int $denominator, int $scale, string $mode = 'none'): int
    {
        if ($denominator === 0) {
            throw new \InvalidArgumentException('Decimal division by zero.');
        }

        $negative = ($numerator < 0) xor ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);
        $scaledNumerator = $numerator * (10 ** $scale);
        $quotient = intdiv($scaledNumerator, $denominator);
        $remainder = $scaledNumerator % $denominator;

        if ($mode !== 'none' && $remainder !== 0) {
            $increment = match ($mode) {
                'ceiling' => ! $negative,
                'floor' => $negative,
                'half_up', 'half_even', 'half_down' => $remainder * 2 > $denominator
                    || ($mode === 'half_up' && $remainder * 2 === $denominator)
                    || ($mode === 'half_even' && $remainder * 2 === $denominator && $quotient % 2 === 1),
                default => false,
            };

            if ($increment) {
                $quotient++;
            }
        }

        return $negative ? -$quotient : $quotient;
    }

    public static function weightedPercent(
        string|int|float $raw,
        int $rawScale,
        string|int|float $weight,
        int $weightScale,
        int $outputScale,
        string $roundingMode = 'none',
    ): string {
        $rawScaled = self::toScaled($raw, $rawScale);
        $weightScaled = self::toScaled($weight, $weightScale);
        $denominator = (10 ** $rawScale) * (10 ** $weightScale) * 100;
        $result = self::divideRounded($rawScaled * $weightScaled, $denominator, $outputScale, $roundingMode);

        return self::fromScaled($result, $outputScale);
    }
}
