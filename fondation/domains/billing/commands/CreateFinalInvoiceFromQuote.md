---
id: BIL-CMD-CREATE-FINAL-INVOICE-FROM-QUOTE
title: CreateFinalInvoiceFromQuote
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# CreateFinalInvoiceFromQuote

## Objectif

Créer l'unique `FinalInvoice` Draft pour le reliquat d'une Quote acceptée.

## Agrégats concernés

`Quote` et une nouvelle `Invoice`, modifiées atomiquement.

## Acteur et autorité

Membre autorisé par `billing.invoices.create`.

## Données d'entrée

```text
WorkspaceId
QuoteId
InvoiceId
ExpectedQuoteRevision
CreateFinalInvoiceFromQuoteRequestId
ActorContext
```

`ExpectedQuoteRevision` est l'`ExpectedRevision` de l'agrégat Quote.

## Préconditions et traitement

- Quote `Accepted` sans `FinalInvoice` ;
- l'éventuelle `DepositInvoice` est émise ; un dépôt encore Draft bloque la
  finale et un dépôt abandonné doit avoir libéré sa réservation ;
- reliquat positif après l'éventuel dépôt ;
- Invoice `Final` créée en `Draft` depuis les lignes acceptées et l'allocation ;
- réservation atomique dans `InvoicingSummary`.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-008`, `BIL-INV-015`–`BIL-INV-017`,
`BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `InvoiceCreated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`CalculationConflict`, `Conflict`.

## Idempotence

`CreateFinalInvoiceFromQuoteRequestId` est obligatoire. Un rejeu retourne le
même Draft ; une seconde allocation est refusée.
