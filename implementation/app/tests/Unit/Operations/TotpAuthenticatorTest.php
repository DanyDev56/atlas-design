<?php

declare(strict_types=1);

namespace Tests\Unit\Operations;

use Atlas\Modules\Operations\Domain\OperatorRecoveryCodes;
use Atlas\Modules\Operations\Domain\TotpAuthenticator;
use PHPUnit\Framework\TestCase;

final class TotpAuthenticatorTest extends TestCase
{
    public function test_it_matches_the_rfc_vector_and_rejects_a_replayed_timestep(): void
    {
        $authenticator = new TotpAuthenticator;
        $at = (new \DateTimeImmutable('@59'))->setTimezone(new \DateTimeZone('UTC'));
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('287082', $authenticator->code($secret, $at));
        self::assertSame(1, $authenticator->verify($secret, '287 082', $at));
        self::assertNull($authenticator->verify($secret, '287082', $at, 1));
        self::assertNull($authenticator->verify($secret, '000000', $at));
    }

    public function test_recovery_code_hashing_is_normalized_and_peppered(): void
    {
        $codes = new OperatorRecoveryCodes('first-pepper');

        self::assertSame($codes->hash('ABCD-EFGH-JK23'), $codes->hash('abcd efgh jk23'));
        self::assertNotSame(
            $codes->hash('ABCD-EFGH-JK23'),
            (new OperatorRecoveryCodes('second-pepper'))->hash('ABCD-EFGH-JK23'),
        );
        self::assertCount(8, $codes->generate());
    }
}
