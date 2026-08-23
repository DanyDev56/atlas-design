<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Domain;

final class OperatorRecoveryCodes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly string $pepper) {}

    /** @return list<string> */
    public function generate(int $count = 8): array
    {
        $codes = [];
        for ($index = 0; $index < $count; $index++) {
            $raw = '';
            for ($character = 0; $character < 12; $character++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[] = substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4);
        }

        return $codes;
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), $this->pepper);
    }

    private function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');
    }
}
