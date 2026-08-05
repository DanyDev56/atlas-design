---
id: BIL-CMD-ACCEPT-QUOTE
title: AcceptQuote
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

# AcceptQuote

## Objectif

Enregistrer l'acceptation irrévocable d'une Quote envoyée.

## Agrégat concerné

`Quote`.

## Acteur et autorité

Destinataire détenant une `PublicDocumentProof` avec capacité d'acceptation.

## Données d'entrée

```text
WorkspaceId
QuoteId
PublicDocumentProof
AcceptanceEvidence
ExpectedRevision
AcceptQuoteRequestId
```

## Préconditions et traitement

- Quote `Sent`, encore valide et preuve non révoquée ;
- identité ou consentement minimal requis par la politique ;
- passage terminal à `Accepted` avec instant et preuve auditables.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-010`, `BIL-INV-013`–`BIL-INV-016`,
`BIL-INV-033`, `BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `QuoteAccepted`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

`AcceptQuoteRequestId` est obligatoire. La même acceptation retourne le résultat
initial ; toute réponse terminale incompatible est refusée.
