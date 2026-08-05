---
id: BIL-CMD-DISCARD-CREDIT-NOTE-DRAFT
title: DiscardCreditNoteDraft
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

# DiscardCreditNoteDraft

## Objectif

Abandonner une CreditNote jamais émise.

## Agrégat concerné

`CreditNote`.

## Acteur et autorité

Membre autorisé par `billing.credit-notes.discard`.

## Données d'entrée

```text
WorkspaceId
CreditNoteId
DiscardReason
ExpectedRevision
DiscardCreditNoteDraftRequestId
ActorContext
```

## Préconditions et traitement

- CreditNote en `Draft` ;
- motif obligatoire ;
- passage terminal à `Discarded` sans suppression physique.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-003`, `BIL-INV-028`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `CreditNoteDiscarded`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`DiscardCreditNoteDraftRequestId` est obligatoire. Le même abandon retourne son
résultat initial.
