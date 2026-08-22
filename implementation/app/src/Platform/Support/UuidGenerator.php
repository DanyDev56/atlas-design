<?php

declare(strict_types=1);

namespace Atlas\Platform\Support;

final class UuidGenerator
{
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);
        $hex = bin2hex($bytes);

        return self::format($hex);
    }

    public static function fromName(string $name): string
    {
        $bytes = substr(hash('sha256', $name, true), 0, 16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x50);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return self::format(bin2hex($bytes));
    }

    private static function format(string $hex): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
