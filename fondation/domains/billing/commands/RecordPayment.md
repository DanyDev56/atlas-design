---
id: BIL-CMD-RECORD-PAYMENT
title: RecordPayment
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

# RecordPayment

## Objectif

Enregistrer un encaissement manuel et l'appliquer au solde d'une Invoice.

## Agrégat concerné

`Invoice`, qui contient le nouveau `Payment`.

## Acteur et autorité

Membre autorisé par `billing.payments.record`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
PaymentId
PaymentDetails
OverpaymentDisposition?
ExpectedRevision
RecordPaymentRequestId
ActorContext
```

## Préconditions et traitement

- Invoice `Issued` avec devise identique au Payment ;
- référence d'encaissement non déjà enregistrée selon la politique ;
- application limitée au solde avant commande ;
- disposition obligatoire si une somme reste non appliquée ;
- Payment, nouveau solde et état de règlement commis atomiquement.

## Invariants concernés

`BIL-INV-001`–`BIL-INV-005`, `BIL-INV-020`–`BIL-INV-026`,
`BIL-INV-031`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `PaymentRecorded` ;
- `PaymentAppliedToInvoice` ;
- `InvoiceBalanceChanged` ;
- `InvoiceSettled` et `InvoicePaid` si le solde devient nul.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`BalanceConflict`, `Conflict`.

## Idempotence

`RecordPaymentRequestId` est obligatoire. Le même encaissement retourne le
Payment initial ; la même référence avec un autre montant est refusée.
