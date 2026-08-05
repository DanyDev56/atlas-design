---
id: BIL-CMD-CONFIRM-QUOTE-SENT
title: ConfirmQuoteSent
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

# ConfirmQuoteSent

## Objectif

Confirmer que le fournisseur de livraison a accepté la transmission.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Acteur système autorisé par `billing.quotes.confirm-sent` et corrélé à la
demande de livraison initiale.

## Données d'entrée

```text
WorkspaceId
QuoteId
DeliveryReceipt
ExpectedRevision
ConfirmQuoteSentRequestId
SystemActorContext
```

## Préconditions et traitement

- Quote `Sending` ;
- reçu authentique pour la même demande et le même Workspace ;
- passage à `Sent` sans affirmer une réception humaine.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-034`, `BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `QuoteSent`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`ConfirmQuoteSentRequestId` est obligatoire et dérivé du reçu fournisseur. Un
rejeu du même reçu retourne le fait initial.
