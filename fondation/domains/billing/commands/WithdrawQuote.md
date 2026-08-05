---
id: BIL-CMD-WITHDRAW-QUOTE
title: WithdrawQuote
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

# WithdrawQuote

## Objectif

Retirer explicitement une Quote non encore répondue.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Membre autorisé par `billing.quotes.withdraw`.

## Données d'entrée

```text
WorkspaceId
QuoteId
WithdrawalReason
ExpectedRevision
WithdrawQuoteRequestId
ActorContext
```

## Préconditions et traitement

- Quote `Sending` ou `Sent` ;
- aucune réponse terminale déjà enregistrée ;
- passage terminal à `Withdrawn` et révocation de la preuve publique.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-013`, `BIL-INV-033`,
`BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `QuoteWithdrawn`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`WithdrawQuoteRequestId` est obligatoire. Un rejeu identique retourne le retrait
initial.
