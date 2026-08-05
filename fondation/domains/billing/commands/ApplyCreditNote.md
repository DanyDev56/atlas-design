---
id: BIL-CMD-APPLY-CREDIT-NOTE
title: ApplyCreditNote
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

# ApplyCreditNote

## Objectif

Appliquer une CreditNote émise au solde de son Invoice source.

## Agrégats concernés

`CreditNote` et `Invoice`, modifiées atomiquement.

## Acteur et autorité

Membre autorisé par `billing.credit-notes.apply`.

## Données d'entrée

```text
WorkspaceId
CreditNoteId
InvoiceId
AmountToApply
RemainderDisposition?
ExpectedCreditNoteRevision
ExpectedInvoiceRevision
ApplyCreditNoteRequestId
ActorContext
```

Les deux champs de révision sont les `ExpectedRevision` des agrégats concernés.

## Préconditions et traitement

- CreditNote `Issued` et Invoice source `Issued` ;
- même Workspace, même devise et lien source exact ;
- montant positif, au plus égal au total de l'avoir et au solde courant ;
- disposition `RefundDue` ou `ClientCredit` obligatoire pour tout reliquat ;
- passage de la CreditNote à `Applied`, reliquat explicite et solde recalculé.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`, `BIL-INV-020`–`BIL-INV-022`,
`BIL-INV-027`–`BIL-INV-031`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `CreditNoteAppliedToInvoice` ;
- `InvoiceBalanceChanged` ;
- `InvoiceSettled` si le solde devient nul.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `BalanceConflict`, `Conflict`.

## Idempotence

`ApplyCreditNoteRequestId` est obligatoire. Un rejeu retourne l'application
initiale ; un autre montant après application est refusé.
