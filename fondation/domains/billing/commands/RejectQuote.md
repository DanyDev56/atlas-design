---
id: BIL-CMD-REJECT-QUOTE
title: RejectQuote
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

# RejectQuote

## Objectif

Enregistrer le rejet irrévocable d'une Quote envoyée.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Destinataire détenant une `PublicDocumentProof` avec capacité de rejet.

## Données d'entrée

```text
WorkspaceId
QuoteId
PublicDocumentProof
RejectionReason?
ExpectedRevision
RejectQuoteRequestId
```

## Préconditions et traitement

- Quote `Sent`, encore valide et preuve non révoquée ;
- raison optionnelle bornée et assainie ;
- passage terminal à `Rejected` avec preuve auditable.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-013`, `BIL-INV-033`,
`BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `QuoteRejected`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

`RejectQuoteRequestId` est obligatoire. La même réponse retourne le résultat
initial ; toute réponse terminale incompatible est refusée.
