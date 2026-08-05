---
id: BIL-CMD-ISSUE-INVOICE
title: IssueInvoice
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

# IssueInvoice

## Objectif

Émettre et figer une Invoice qui devient une créance.

## Agrégats concernés

`Invoice` et la `DocumentNumberSequence` Invoice correspondante.

## Acteur et autorité

Membre autorisé par `billing.invoices.issue`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
IssueDate
ExpectedRevision
IssueInvoiceRequestId
ActorContext
```

## Préconditions et traitement

- Invoice `Draft`, valide et non vide ;
- contextes CRM et Workspace relus et snapshots complétés ;
- échéance calculée par la politique effective ;
- numéro alloué sans réutilisation ;
- état `Issued`, modèle de rendu et outbox atomiques.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-009`, `BIL-INV-017`–`BIL-INV-022`,
`BIL-INV-032`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `InvoiceIssued`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `CalculationConflict`, `NumberingUnavailable`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`IssueInvoiceRequestId` est obligatoire. Un rejeu ne réserve ni nouveau numéro
ni nouvel artefact source.
