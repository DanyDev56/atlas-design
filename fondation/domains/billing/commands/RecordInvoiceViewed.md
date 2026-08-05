---
id: BIL-CMD-RECORD-INVOICE-VIEWED
title: RecordInvoiceViewed
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

# RecordInvoiceViewed

## Objectif

Enregistrer un accès public vérifié à une Invoice émise.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Passerelle publique sous `billing.invoices.record-view`, avec une
`PublicDocumentProof` valide.

## Données d'entrée

```text
WorkspaceId
InvoiceId
PublicDocumentProof
ViewContext
ExpectedRevision
RecordInvoiceViewedRequestId
```

## Préconditions et traitement

- preuve bornée à cette Invoice et à la lecture ;
- Invoice `Issued` ;
- données techniques minimisées.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-017`, `BIL-INV-033`, `BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `InvoiceViewed`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`RecordInvoiceViewedRequestId` est obligatoire. Un même affichage technique ne
produit qu'un fait.
