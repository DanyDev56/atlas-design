---
id: BIL-CMD-UPDATE-QUOTE-DRAFT
title: UpdateQuoteDraft
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

# UpdateQuoteDraft

## Objectif

Modifier le contenu commercial d'une Quote encore `Draft`.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Membre autorisé par `billing.quotes.update-draft`.

## Données d'entrée

```text
WorkspaceId
QuoteId
QuoteDraftChanges
ExpectedRevision
UpdateQuoteDraftRequestId
ActorContext
```

## Préconditions et traitement

- Quote du même Workspace en `Draft` ;
- champs modifiables et lignes valides ;
- recalcul déterministe des totaux ;
- aucun numéro ni snapshot figé n'est modifié.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-006`, `BIL-INV-010`,
`BIL-INV-011`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `QuoteDraftUpdated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`CalculationConflict`, `Conflict`.

## Idempotence

`UpdateQuoteDraftRequestId` est obligatoire. Une répétition identique retourne
la révision initialement produite.
