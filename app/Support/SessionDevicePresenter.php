<?php

namespace App\Support;

final class SessionDevicePresenter
{
    public static function browser(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        return match (true) {
            preg_match('/Edg(?:A|iOS)?\//i', $userAgent) === 1 => 'Microsoft Edge',
            preg_match('/OPR\//i', $userAgent) === 1 => 'Opera',
            preg_match('/SamsungBrowser\//i', $userAgent) === 1 => 'Samsung Internet',
            preg_match('/(?:Firefox|FxiOS)\//i', $userAgent) === 1 => 'Firefox',
            preg_match('/(?:Chrome|CriOS)\//i', $userAgent) === 1 => 'Chrome',
            preg_match('/Safari\//i', $userAgent) === 1 => 'Safari',
            default => 'Unknown browser',
        };
    }

    public static function platform(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        return match (true) {
            preg_match('/Windows NT/i', $userAgent) === 1 => 'Windows',
            preg_match('/Android/i', $userAgent) === 1 => 'Android',
            preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'iOS',
            preg_match('/Macintosh|Mac OS X/i', $userAgent) === 1 => 'macOS',
            preg_match('/Linux/i', $userAgent) === 1 => 'Linux',
            default => 'Unknown platform',
        };
    }

    public static function deviceType(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        return match (true) {
            preg_match('/iPad|Tablet/i', $userAgent) === 1 => 'Tablet',
            preg_match('/Android/i', $userAgent) === 1 && preg_match('/Mobile/i', $userAgent) !== 1 => 'Tablet',
            preg_match('/Mobile|iPhone|Android/i', $userAgent) === 1 => 'Mobile',
            preg_match('/Windows NT|Macintosh|Linux/i', $userAgent) === 1 => 'Desktop',
            default => 'Unknown device',
        };
    }
}
