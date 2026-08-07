<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

final class LineCalculator
{
    /** @param list<array<string, mixed>> $lines */
    public static function totalCents(array $lines): int
    {
        $total = 0;

        foreach ($lines as $line) {
            $quantity = (int) ($line['quantity'] ?? 1);
            $unitPrice = (int) ($line['unit_price_cents'] ?? 0);
            $total += $quantity * $unitPrice;
        }

        return $total;
    }
}
