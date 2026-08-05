---
id: BIL-CMD-SEND-QUOTE
title: SendQuote
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

# SendQuote

## Objectif

Finaliser une Quote et demander sa livraison.

## Agrégats concernés

`Quote` et la `DocumentNumberSequence` Quote correspondante, dans une seule
transaction métier.

## Acteur et autorité

Membre autorisé par `billing.quotes.send`.

## Données d'entrée

```text
WorkspaceId
QuoteId
DeliveryInstruction
ExpectedRevision
SendQuoteRequestId
ActorContext
```

## Préconditions et traitement

- Quote `Draft`, valide et non vide ;
- contextes CRM et Workspace courants encore cohérents ;
- allocation non réutilisable du numéro ;
- snapshots, totaux et validité figés ;
- preuve publique créée, état `Sending` et outbox atomiques.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-013`, `BIL-INV-032`–`BIL-INV-038`.

## Événements produits

- `QuoteSendRequested`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `CalculationConflict`, `NumberingUnavailable`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`SendQuoteRequestId` est obligatoire. Les retries réutilisent la même demande
de livraison et ne réservent jamais un second numéro.
