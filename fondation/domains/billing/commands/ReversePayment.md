---
id: BIL-CMD-REVERSE-PAYMENT
title: ReversePayment
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

# ReversePayment

## Objectif

Inverser intégralement un Payment enregistré par erreur.

## Agrégat concerné

`Invoice` contenant le `Payment`.

## Acteur et autorité

Membre autorisé par `billing.payments.reverse`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
PaymentId
ReversalReason
ExpectedRevision
ReversePaymentRequestId
ActorContext
```

## Préconditions et traitement

- Payment `Recorded`, non déjà inversé ;
- motif obligatoire et borné ;
- retrait intégral de son application et recalcul atomique du solde ;
- passage terminal du Payment à `Reversed`.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-003`, `BIL-INV-020`–`BIL-INV-026`,
`BIL-INV-031`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `PaymentReversed` ;
- `PaymentApplicationReversed` ;
- `InvoiceBalanceChanged` ;
- `InvoiceSettlementReopened` si le solde nul redevient positif.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`BalanceConflict`, `Conflict`.

## Idempotence

`ReversePaymentRequestId` est obligatoire. Le même motif retourne l'inversion
initiale ; une seconde inversion incompatible est refusée.
