---
id: BIL-CMD-UPDATE-CREDIT-NOTE-DRAFT
title: UpdateCreditNoteDraft
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

# UpdateCreditNoteDraft

## Objectif

Modifier les lignes correctives d'une CreditNote `Draft`.

## Agrégat concerné

`CreditNote`.

## Acteur et autorité

Membre autorisé par `billing.credit-notes.update-draft`.

## Données d'entrée

```text
WorkspaceId
CreditNoteId
CreditNoteDraftChanges
ExpectedRevision
UpdateCreditNoteDraftRequestId
ActorContext
```

## Préconditions et traitement

- CreditNote en `Draft` ;
- Invoice source et devise inchangées ;
- lignes et totaux correctifs recalculés de façon déterministe.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-006`, `BIL-INV-027`–`BIL-INV-030`,
`BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `CreditNoteDraftUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`CalculationConflict`, `Conflict`.

## Idempotence

`UpdateCreditNoteDraftRequestId` est obligatoire. Un rejeu identique retourne la
révision initiale.
