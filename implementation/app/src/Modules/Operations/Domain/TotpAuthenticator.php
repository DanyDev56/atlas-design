<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Domain;

final class TotpAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountLabel);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer),
        );
    }

    public function verify(string $secret, string $code, \DateTimeImmutable $at, ?int $lastUsedTimestep = null): ?int
    {
        $normalized = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($normalized) !== 6) {
            return null;
        }

        $currentTimestep = intdiv($at->getTimestamp(), 30);
        foreach ([0, -1, 1] as $offset) {
            $timestep = $currentTimestep + $offset;
            if ($lastUsedTimestep !== null && $timestep <= $lastUsedTimestep) {
                continue;
            }

            if (hash_equals($this->codeAt($secret, $timestep), $normalized)) {
                return $timestep;
            }
        }

        return null;
    }

    public function code(string $secret, \DateTimeImmutable $at): string
    {
        return $this->codeAt($secret, intdiv($at->getTimestamp(), 30));
    }

    private function codeAt(string $secret, int $timestep): string
    {
        $counter = pack('N2', intdiv($timestep, 4294967296), $timestep % 4294967296);
        $hash = hash_hmac('sha1', $counter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = unpack('N', substr($hash, $offset, 4));
        $value = (($binary[1] ?? 0) & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $buffer = 0;
        $bits = 0;
        $decoded = '';

        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                throw new \DomainException('Invalid TOTP secret.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $decoded;
    }
}
