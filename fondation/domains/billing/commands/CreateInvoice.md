---
id: BIL-CMD-CREATE-INVOICE
title: CreateInvoice
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

# CreateInvoice

## Objectif

Créer une Invoice autonome en `Draft`.

## Agrégat concerné

Nouvel agrégat `Invoice` de kind `Standard`.

## Acteur et autorité

Membre autorisé par `billing.invoices.create`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
ClientId
InvoiceDraft
CreateInvoiceRequestId
ActorContext
```

## Préconditions et traitement

- Client courant valide dans CRM ;
- identité Workspace et devise disponibles ;
- création en `Draft` avec snapshots provisoires et totaux calculés ;
- aucune Quote source n'est inventée.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-002`, `BIL-INV-004`–`BIL-INV-008`,
`BIL-INV-017`, `BIL-INV-035`, `BIL-INV-037`, `BIL-INV-038`.

## Événements produits

- `InvoiceCreated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `ReferenceConflict`, `CalculationConflict`,
`Conflict`, `TemporarilyUnavailable`.

## Idempotence

`CreateInvoiceRequestId` est obligatoire. La même intention retourne l'Invoice
initiale ; une réutilisation incompatible échoue.
