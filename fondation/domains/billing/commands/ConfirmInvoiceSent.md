---
id: BIL-CMD-CONFIRM-INVOICE-SENT
title: ConfirmInvoiceSent
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

# ConfirmInvoiceSent

## Objectif

Confirmer que le fournisseur a accepté la transmission d'une Invoice.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Acteur système autorisé par `billing.invoices.confirm-sent` et corrélé à la
demande initiale.

## Données d'entrée

```text
WorkspaceId
InvoiceId
DeliveryReceipt
ExpectedRevision
ConfirmInvoiceSentRequestId
SystemActorContext
```

## Préconditions et traitement

- Invoice `Issued` avec demande de livraison correspondante ;
- reçu authentique et non déjà attribué ;
- projection de livraison mise à jour sans modifier la créance.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-018`, `BIL-INV-034`, `BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `InvoiceSent`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`ConfirmInvoiceSentRequestId` est obligatoire et dérivé du reçu fournisseur. Un
rejeu retourne le fait initial.
