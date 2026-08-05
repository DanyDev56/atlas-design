---
id: BIL-CMD-EXPIRE-QUOTE
title: ExpireQuote
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

# ExpireQuote

## Objectif

Matérialiser l'expiration temporelle d'une Quote sans réponse.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Scheduler autorisé par `billing.quotes.expire` avec preuve d'horloge.

## Données d'entrée

```text
WorkspaceId
QuoteId
ClockProof
ExpectedRevision
ExpireQuoteRequestId
SystemActorContext
```

## Préconditions et traitement

- Quote `Sent` et `Now > ValidUntil` ;
- preuve d'horloge authentique et suffisamment fraîche ;
- passage terminal à `Expired` et révocation des capacités de réponse.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-013`, `BIL-INV-014`,
`BIL-INV-033`, `BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `QuoteExpired`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`ExpireQuoteRequestId` est obligatoire et déterministe par Quote et échéance. Un
rejeu après expiration retourne le résultat initial.
