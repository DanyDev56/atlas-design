<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Rendering;

final class DeterministicPdfRenderer
{
    /** @param list<string> $lines */
    public function render(array $lines): string
    {
        $commands = ["BT", "/F1 11 Tf", "50 790 Td"];
        foreach (array_slice($lines, 0, 42) as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -18 Td';
            }
            $commands[] = sprintf('(%s) Tj', $this->escape($line));
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands)."\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($stream), $stream),
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            count($objects) + 1,
            $xrefOffset,
        );

        return $pdf;
    }

    private function escape(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii !== false ? $ascii : $value);
    }
}
