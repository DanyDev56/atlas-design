<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Application;

use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresDocumentArtifactRepository;
use Atlas\Modules\Billing\Infrastructure\Rendering\DeterministicPdfRenderer;

final class BillingDocumentArtifactService
{
    public function __construct(
        private readonly PostgresDocumentArtifactRepository $artifacts,
        private readonly DeterministicPdfRenderer $renderer,
    ) {}

    /**
     * @param list<array<string, mixed>> $documentLines
     * @param array<string, mixed> $clientSnapshot
     */
    public function create(
        string $workspaceId,
        string $documentType,
        string $documentId,
        int $documentVersion,
        string $documentNumber,
        array $documentLines,
        int $totalCents,
        string $currency,
        array $clientSnapshot,
    ): void {
        $labels = [
            'quote' => 'DEVIS',
            'invoice' => 'FACTURE',
            'credit_note' => 'AVOIR',
        ];
        $lines = [
            'ATLAS',
            $labels[$documentType] ?? strtoupper($documentType),
            'Numero : '.$documentNumber,
            'Client : '.(string) ($clientSnapshot['display_name'] ?? 'Client'),
            '',
        ];

        foreach ($documentLines as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            $unitPrice = (int) ($line['unit_price_cents'] ?? 0);
            $lines[] = sprintf(
                '%s | %d x %.2f %s | %.2f %s',
                (string) ($line['description'] ?? ''),
                $quantity,
                $unitPrice / 100,
                $currency,
                ($quantity * $unitPrice) / 100,
                $currency,
            );
        }

        $lines[] = '';
        $lines[] = sprintf('TOTAL : %.2f %s', $totalCents / 100, $currency);
        $lines[] = 'Document genere par Atlas.';

        $filename = strtolower(str_replace('_', '-', $documentType)).'-'.$documentNumber.'.pdf';
        $this->artifacts->store(
            $workspaceId,
            $documentType,
            $documentId,
            $documentVersion,
            $filename,
            $this->renderer->render($lines),
        );
    }

    /** @return array<string, mixed> */
    public function get(string $workspaceId, string $documentType, string $documentId): array
    {
        $artifact = $this->artifacts->latest($workspaceId, $documentType, $documentId);
        if ($artifact === null) {
            throw new \DomainException('Document artifact not found.');
        }

        return $artifact;
    }
}
