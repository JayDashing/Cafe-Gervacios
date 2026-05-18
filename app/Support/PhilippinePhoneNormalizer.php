<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Normalize Philippine mobile numbers to SMS-provider format: 639XXXXXXXXX (no +).
 */
final class PhilippinePhoneNormalizer
{
    /**
     * @throws InvalidArgumentException
     */
    public static function normalize(string $raw): string
    {
        $s = trim($raw);
        $s = preg_replace('/[\s\-\(\)\.]/', '', $s) ?? '';

        if (str_starts_with($s, '+')) {
            $s = substr($s, 1);
        }

        if (! preg_match('/^\d+$/', $s)) {
            throw new InvalidArgumentException('Phone number must contain digits only (after formatting).');
        }

        if (str_starts_with($s, '63') && strlen($s) === 12 && $s[2] === '9') {
            return $s;
        }

        if (str_starts_with($s, '09') && strlen($s) === 11) {
            return '63'.substr($s, 1);
        }

        if (str_starts_with($s, '9') && strlen($s) === 10) {
            return '63'.$s;
        }

        throw new InvalidArgumentException('Please use a valid Philippine mobile (+63 9XX XXX XXXX or 09XX XXX XXXX).');
    }
}
