---
id: BIL-CMD-RECORD-QUOTE-VIEWED
title: RecordQuoteViewed
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

# RecordQuoteViewed

## Objectif

Enregistrer un accès public vérifié à une Quote envoyée.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Passerelle publique sous la capacité `billing.quotes.record-view`, avec une
`PublicDocumentProof` valide.

## Données d'entrée

```text
WorkspaceId
QuoteId
PublicDocumentProof
ViewContext
ExpectedRevision
RecordQuoteViewedRequestId
```

## Préconditions et traitement

- preuve bornée à la lecture de cette Quote ;
- Quote `Sent` ;
- aucune donnée réseau brute inutile n'est conservée.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-013`, `BIL-INV-033`,
`BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `QuoteViewed`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`RecordQuoteViewedRequestId` est obligatoire. Le même affichage technique ne
produit qu'un fait, sans prétendre identifier une personne.
