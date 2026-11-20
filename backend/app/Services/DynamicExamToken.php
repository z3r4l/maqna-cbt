<?php

namespace App\Services;

final class DynamicExamToken
{
    public const PERIOD_SECONDS = 300;
    public const GRACE_SECONDS = 30;

    public static function at(string $seed, int $timestamp): array
    {
        $window = intdiv($timestamp, self::PERIOD_SECONDS);
        return ['accessCode' => strtoupper(substr(hash_hmac('sha256', (string) $window, $seed), 0, 8)),
            'expiresAt' => gmdate('Y-m-d H:i:s', ($window + 1) * self::PERIOD_SECONDS),
            'serverNow' => gmdate('Y-m-d H:i:s', $timestamp), 'ttlSeconds' => self::PERIOD_SECONDS];
    }

    public static function verify(string $seed, string $code, int $timestamp): bool
    {
        $current = self::at($seed, $timestamp)['accessCode'];
        if (hash_equals($current, strtoupper($code))) return true;
        if ($timestamp % self::PERIOD_SECONDS > self::GRACE_SECONDS) return false;
        return hash_equals(self::at($seed, $timestamp - self::PERIOD_SECONDS)['accessCode'], strtoupper($code));
    }
}
