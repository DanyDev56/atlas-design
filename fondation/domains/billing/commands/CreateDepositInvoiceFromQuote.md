---
id: BIL-CMD-CREATE-DEPOSIT-INVOICE-FROM-QUOTE
title: CreateDepositInvoiceFromQuote
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

# CreateDepositInvoiceFromQuote

## Objectif

Créer l'unique `DepositInvoice` Draft d'une Quote acceptée.

## Agrégats concernés

`Quote` et une nouvelle `Invoice`, modifiées atomiquement.

## Acteur et autorité

Membre autorisé par `billing.invoices.create`.

## Données d'entrée

```text
WorkspaceId
QuoteId
InvoiceId
DepositPolicy
ExpectedQuoteRevision
CreateDepositInvoiceFromQuoteRequestId
ActorContext
```

`ExpectedQuoteRevision` est l'`ExpectedRevision` de l'agrégat Quote.

## Préconditions et traitement

- Quote `Accepted`, sans `DepositInvoice` ni `FinalInvoice` ;
- dépôt positif et inférieur ou égal au total accepté ;
- Invoice `Deposit` créée en `Draft` avec provenance et snapshots copiés ;
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

`CreateDepositInvoiceFromQuoteRequestId` est obligatoire. Un rejeu retourne le
même Draft ; une seconde allocation est refusée.
