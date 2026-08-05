---
id: BIL-CMD-CORRECT-INVOICE-METADATA
title: CorrectInvoiceMetadata
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# CorrectInvoiceMetadata

## Objectif

Corriger une métadonnée non financière autorisée d'une Invoice émise.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Membre autorisé par `billing.invoices.correct-metadata`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
MetadataCorrection
CorrectionReason
ExpectedRevision
CorrectInvoiceMetadataRequestId
ActorContext
```

## Préconditions et traitement

- Invoice `Issued` ;
- champ présent dans l'allowlist non financière versionnée ;
- numéro, lignes, prix, taxes, devise, dates financières et snapshots inchangés ;
- ancienne et nouvelle valeur auditées.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-003`, `BIL-INV-008`, `BIL-INV-018`,
`BIL-INV-032`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `InvoiceMetadataCorrected`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`InvariantViolation`, `Conflict`.

## Idempotence

`CorrectInvoiceMetadataRequestId` est obligatoire. La même correction retourne
la révision initiale.
