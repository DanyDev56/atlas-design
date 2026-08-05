---
id: BIL-CMD-CREATE-QUOTE
title: CreateQuote
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

# CreateQuote

## Objectif

Créer une Quote `Draft` pour un Client courant.

## Agrégat concerné

Nouvel agrégat `Quote`.

## Acteur et autorité

Membre autorisé par `billing.quotes.create`.

## Données d'entrée

```text
WorkspaceId
QuoteId
ClientId
OpportunityId?
QuoteDraft
CreateQuoteRequestId
ActorContext
```

## Préconditions et traitement

- Client actif et Opportunity éventuelle cohérente selon les contrats CRM ;
- identité Workspace et devise par défaut disponibles ;
- lignes et dates syntaxiquement valides ;
- création en `Draft` avec snapshots provisoires et totaux calculés.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-002`, `BIL-INV-004`–`BIL-INV-008`,
`BIL-INV-010`, `BIL-INV-035`, `BIL-INV-037`, `BIL-INV-038`.

## Événements produits

- `QuoteCreated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `ReferenceConflict`, `CalculationConflict`,
`Conflict`, `TemporarilyUnavailable`.

## Idempotence

`CreateQuoteRequestId` est obligatoire. La même intention retourne la Quote
initiale ; une réutilisation avec un autre contenu échoue.
