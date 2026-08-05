---
id: BIL-CMD-DISCARD-INVOICE-DRAFT
title: DiscardInvoiceDraft
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

# DiscardInvoiceDraft

## Objectif

Abandonner explicitement une Invoice jamais émise.

## Agrégat concerné

`Invoice`. Si elle provient d'une Quote, la réservation correspondante est
libérée dans la même transaction.

## Acteur et autorité

Membre autorisé par `billing.invoices.discard`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
DiscardReason
ExpectedRevision
ExpectedQuoteRevision?
DiscardInvoiceDraftRequestId
ActorContext
```

## Préconditions et traitement

- Invoice en `Draft` ;
- Quote source éventuelle inchangée depuis la lecture ;
- passage terminal à `Discarded`, sans suppression physique ;
- allocation Quote libérée pour permettre un nouveau Draft.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-003`, `BIL-INV-016`–`BIL-INV-018`,
`BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `InvoiceDiscarded`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`DiscardInvoiceDraftRequestId` est obligatoire. Le même abandon retourne son
résultat initial.
