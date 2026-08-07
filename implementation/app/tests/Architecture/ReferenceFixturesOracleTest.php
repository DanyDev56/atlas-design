<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ReferenceFixturesOracleTest extends TestCase
{
    public function test_mvp_reference_fixtures_match_jq_oracle(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $script = $repoRoot.'/scripts/check-mvp-reference-fixtures.sh';

        if (! is_file($script)) {
            $this->markTestSkipped('Reference fixture checker script is missing.');
        }

        if (trim((string) shell_exec('command -v jq')) === ''
            || trim((string) shell_exec('command -v rg')) === '') {
            $this->markTestSkipped('jq and ripgrep are required to validate reference fixtures.');
        }

        $process = new Process(['bash', $script], $repoRoot);
        $process->setTimeout(120);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "Reference fixture oracle failed:\n".$process->getOutput().$process->getErrorOutput(),
        );
    }
}
