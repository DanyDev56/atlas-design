---
id: BIL-CMD-MARK-INVOICE-OVERDUE
title: MarkInvoiceOverdue
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

# MarkInvoiceOverdue

## Objectif

Matérialiser le passage d'une Invoice en retard.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Scheduler autorisé par `billing.invoices.mark-overdue` avec preuve d'horloge.

## Données d'entrée

```text
WorkspaceId
InvoiceId
ClockProof
ExpectedRevision
MarkInvoiceOverdueRequestId
SystemActorContext
```

## Préconditions et traitement

- Invoice `Issued`, solde positif et échéance strictement dépassée ;
- preuve d'horloge authentique ;
- aucun fait de retard déjà émis pour ce passage.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-020`–`BIL-INV-022`, `BIL-INV-031`,
`BIL-INV-036`–`BIL-INV-038`.

## Événements produits

- `InvoiceOverdue`.

## Erreurs métier

`Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`MarkInvoiceOverdueRequestId` est obligatoire et déterministe par Invoice et
échéance. Un rejeu retourne le fait initial.
