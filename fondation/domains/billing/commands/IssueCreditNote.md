---
id: BIL-CMD-ISSUE-CREDIT-NOTE
title: IssueCreditNote
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

# IssueCreditNote

## Objectif

Émettre et figer une CreditNote correctrice.

## Agrégats concernés

`CreditNote` et la `DocumentNumberSequence` CreditNote correspondante. Les
CreditNotes déjà émises de l'Invoice source sont vérifiées.

## Acteur et autorité

Membre autorisé par `billing.credit-notes.issue`.

## Données d'entrée

```text
WorkspaceId
CreditNoteId
IssueDate
ExpectedRevision
IssueCreditNoteRequestId
ActorContext
```

## Préconditions et traitement

- CreditNote `Draft`, valide et non vide ;
- Invoice source toujours émise ;
- émission sérialisée sur l'`InvoiceId` source et plafond cumulé des corrections
  respecté ;
- numéro alloué, contenu figé et modèle de rendu créé atomiquement.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-004`–`BIL-INV-009`, `BIL-INV-027`–`BIL-INV-030`,
`BIL-INV-032`, `BIL-INV-035`–`BIL-INV-038`.

## Événements produits

- `CreditNoteIssued`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `CalculationConflict`, `NumberingUnavailable`, `Conflict`.

## Idempotence

`IssueCreditNoteRequestId` est obligatoire. Un rejeu ne réserve ni nouveau
numéro ni nouvel artefact source.
