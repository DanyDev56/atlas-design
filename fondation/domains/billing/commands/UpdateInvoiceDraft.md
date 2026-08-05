---
id: BIL-CMD-UPDATE-INVOICE-DRAFT
title: UpdateInvoiceDraft
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

# UpdateInvoiceDraft

## Objectif

Modifier une Invoice `Draft` sans changer sa provenance.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Membre autorisé par `billing.invoices.update-draft`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
InvoiceDraftChanges
ExpectedRevision
UpdateInvoiceDraftRequestId
ActorContext
```

## Préconditions et traitement

- Invoice en `Draft` ;
- kind, Quote source et Client immuables ;
- pour une Invoice issue d'une Quote, le total réservé reste inchangé ;
- totaux recalculés de façon déterministe.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-006`, `BIL-INV-016`–`BIL-INV-018`,
`BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `InvoiceDraftUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`CalculationConflict`, `Conflict`.

## Idempotence

`UpdateInvoiceDraftRequestId` est obligatoire. Un rejeu identique retourne la
révision initialement produite.
